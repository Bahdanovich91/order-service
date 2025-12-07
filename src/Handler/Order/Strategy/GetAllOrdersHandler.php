<?php

declare(strict_types=1);

namespace App\Handler\Order\Strategy;

use App\Entity\Order;
use App\Enum\OrderAction;
use App\Repository\OrderRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.orderHandlers')]
final readonly class GetAllOrdersHandler implements OrderHandlerInterface
{
    public function __construct(
        private OrderRepository $orderRepository
    ) {
    }

    public function isApplicable(OrderAction $action): bool
    {
        return OrderAction::GetAll->value === $action->value;
    }

    public static function getDefaultPriority(): int
    {
        return OrderAction::GetAll->priority();
    }

    public function __invoke(): array
    {
        return $this->orderRepository->findAll();
    }
}
