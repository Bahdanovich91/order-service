<?php

declare(strict_types=1);

namespace App\Handler\Order\Strategy;

use App\Enum\OrderAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.orderHandlers')]
interface OrderHandlerInterface
{
    public function isApplicable(OrderAction $action): bool;

    public static function getDefaultPriority(): int;
}
