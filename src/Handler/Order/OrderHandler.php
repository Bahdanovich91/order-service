<?php

declare(strict_types=1);

namespace App\Handler\Order;

use App\Dto\CreateOrderDto;
use App\Enum\OrderAction;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class OrderHandler
{
    private iterable $handlers;

    public function __construct(
        #[TaggedIterator('app.orderHandlers')]
        iterable $handlers,
    ) {
        $this->handlers = $handlers;
    }

    public function __invoke(OrderAction $action, ?CreateOrderDto $dto = null): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->isApplicable($action)) {
                return $handler($dto);
            }
        }

        throw new \RuntimeException("No applicable handler found for action: $action->value");
    }
}
