<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderAction: string
{
    public function priority(): int
    {
        return match ($this) {
            OrderAction::GetAll   => 1,
            OrderAction::Create   => 2,
            OrderAction::Complete => 3,
            default               => 4
        };
    }

    case Create   = 'create';
    case Complete = 'complete';
    case GetAll   = 'getAll';
    case Get      = 'get';
}
