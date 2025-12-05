<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateOrderDto;
use App\Entity\Order;
use App\Exception\InsufficientBalanceException;
use App\Exception\InsufficientInventoryException;
use App\Repository\OrderRepository;
use Psr\Log\LoggerInterface;

readonly class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private KafkaService    $kafkaService,
        private LoggerInterface $logger,
        private string          $orderEventsTopic,
        private string          $inventoryCommandsTopic,
        private string          $balanceCommandsTopic
    ) {
    }

    public function createOrder(CreateOrderDto $dto): Order
    {
        $totalAmount = $this->calculateTotalAmount($dto->items);

        $order = new Order();
        $order->setUserId($dto->userId);
        $order->setStatus('pending');
        $order->setTotalAmount((string) $totalAmount);
        $order->setItems($dto->items);

        $this->orderRepository->save($order, true);

        $this->kafkaService->sendEvent($this->orderEventsTopic, [
            'type' => 'order_created',
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'total_amount' => $order->getTotalAmount(),
            'items' => $order->getItems(),
            'timestamp' => $order->getCreatedAt()->format('c'),
        ]);

        $this->logger->info('Order created', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
        ]);

        $correlationId = 'order_' . $order->getId() . '_' . time();

        foreach ($dto->items as $item) {
            $this->kafkaService->sendCommand($this->inventoryCommandsTopic, [
                'command' => 'check_availability',
                'order_id' => $order->getId(),
                'product_id' => $item['product_id'],
                'warehouse_id' => $item['warehouse_id'],
                'quantity' => $item['quantity'],
                'correlation_id' => $correlationId,
            ], $correlationId);
        }

        // Отправляем команду для проверки баланса через Kafka
        $this->kafkaService->sendCommand($this->balanceCommandsTopic, [
            'command' => 'check_balance',
            'order_id' => $order->getId(),
            'user_id' => $dto->userId,
            'amount' => $totalAmount,
            'correlation_id' => $correlationId,
        ], $correlationId);

        $this->logger->info('Sending execution commands after validation', [
            'order_id' => $order->getId(),
        ]);

        $this->reserveInventoryAndWithdraw($order);

        return $order;
    }

    public function processOrderValidation(int $orderId, bool $inventoryAvailable, bool $balanceSufficient): void
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            $this->logger->error('Order not found for validation', ['order_id' => $orderId]);
            return;
        }

        if (!$inventoryAvailable) {
            $order->setStatus('failed');
            $this->orderRepository->save($order, true);
            throw new InsufficientInventoryException('Недостаточно товара на складе');
        }

        if (!$balanceSufficient) {
            $order->setStatus('failed');
            $this->orderRepository->save($order, true);
            throw new InsufficientBalanceException('Недостаточно средств на балансе');
        }

        // Если всё ок, резервируем товары и списываем средства
        $this->reserveInventoryAndWithdraw($order);
    }

    private function reserveInventoryAndWithdraw(Order $order): void
    {
        $correlationId = 'order_' . $order->getId() . '_reserve_' . time();

        foreach ($order->getItems() as $item) {
            $this->kafkaService->sendCommand($this->inventoryCommandsTopic, [
                'command' => 'reserve',
                'order_id' => $order->getId(),
                'product_id' => $item['product_id'],
                'warehouse_id' => $item['warehouse_id'],
                'quantity' => $item['quantity'],
                'correlation_id' => $correlationId,
            ], $correlationId);
        }

        $this->kafkaService->sendCommand($this->balanceCommandsTopic, [
            'command' => 'withdraw',
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'amount' => $order->getTotalAmount(),
            'correlation_id' => $correlationId,
        ], $correlationId);

        $order->setStatus('processing');
        $this->orderRepository->save($order, true);
    }

    public function completeOrder(int $orderId): void
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            $this->logger->error('Order not found for completion', ['order_id' => $orderId]);
            return;
        }

        $order->setStatus('completed');
        $this->orderRepository->save($order, true);

        $this->kafkaService->sendEvent($this->orderEventsTopic, [
            'type' => 'order_completed',
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'timestamp' => $order->getUpdatedAt()?->format('c') ?? $order->getCreatedAt()->format('c'),
        ]);

        $this->logger->info('Order completed', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
        ]);
    }

    /**
     * @param array<int, array{product_id: int, quantity: int, warehouse_id: int}> $items
     */
    private function calculateTotalAmount(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += $item['quantity'] * 100.0;
        }

        return $total;
    }
}
