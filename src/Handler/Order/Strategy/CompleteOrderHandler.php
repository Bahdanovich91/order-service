<?php

declare(strict_types=1);

namespace App\Handler\Order\Strategy;

use App\Enum\OrderAction;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\KafkaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class CompleteOrderHandler implements OrderHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository        $orderRepository,
        private KafkaService           $kafkaService,
        private LoggerInterface        $logger,
        private string                 $orderEventsTopic,
    ) {
    }

    public function isApplicable(OrderAction $action): bool
    {
        return OrderAction::Complete->value === $action->value;
    }

    public static function getDefaultPriority(): int
    {
        return OrderAction::Complete->priority();
    }

    public function __invoke(int $orderId): void
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            $this->logger->error('Order not found', ['order_id' => $orderId]);

            return;
        }

        $order->setStatus(OrderStatus::Completed->value);
        $this->entityManager->flush();

        $this->kafkaService->sendEvent($this->orderEventsTopic, [
            'type' => 'order_completed',
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'timestamp' => $order->getUpdatedAt()?->format('c') ?? $order->getCreatedAt()->format('c'),
        ]);
    }
}
