<?php

declare(strict_types=1);

namespace App\Handler\Order\Strategy;

use App\Dto\CreateOrderDto;
use App\Entity\Order;
use App\Enum\OrderAction;
use App\Enum\OrderStatus;
use App\Service\KafkaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.orderHandlers')]
final readonly class CreateOrderHandler implements OrderHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KafkaService           $kafkaService,
        private string                 $orderEventsTopic,
        private string                 $inventoryCommandsTopic,
        private string                 $balanceCommandsTopic,
    ) {
    }

    public function isApplicable(OrderAction $action): bool
    {
        return OrderAction::Create->value === $action->value;
    }

    private function calculateTotalAmount(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += $item['quantity'] * 100.0;
        }

        return $total;
    }

    private function sendKafkaEvents(Order $order, array $items): void
    {
        $correlationId = 'order_' . $order->getId() . '_' . time();

        $this->kafkaService->sendEvent($this->orderEventsTopic, [
            'type'         => 'order_created',
            'order_id'     => $order->getId(),
            'user_id'      => $order->getUserId(),
            'total_amount' => $order->getTotalAmount(),
            'items'        => $items,
            'timestamp'    => $order->getCreatedAt()->format('c'),
        ]);

        foreach ($items as $item) {
            $this->kafkaService->sendCommand($this->inventoryCommandsTopic, [
                'command'        => 'check_availability',
                'order_id'       => $order->getId(),
                'product_id'     => $item['product_id'],
                'warehouse_id'   => $item['warehouse_id'],
                'quantity'       => $item['quantity'],
                'correlation_id' => $correlationId,
            ], $correlationId);
        }

        $this->kafkaService->sendCommand($this->balanceCommandsTopic, [
            'command'        => 'check_balance',
            'order_id'       => $order->getId(),
            'user_id'        => $order->getUserId(),
            'amount'         => $order->getTotalAmount(),
            'correlation_id' => $correlationId,
        ], $correlationId);
    }

    public static function getDefaultPriority(): int
    {
        return OrderAction::Create->priority();
    }

    public function __invoke(CreateOrderDto $dto): Order
    {
        $totalAmount = $this->calculateTotalAmount($dto->items);

        $order = new Order();
        $order->setUserId($dto->userId);
        $order->setStatus(OrderStatus::Pending->value);
        $order->setTotalAmount((string)$totalAmount);
        $order->setItems($dto->items);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->sendKafkaEvents($order, $dto->items);

        return $order;
    }
}
