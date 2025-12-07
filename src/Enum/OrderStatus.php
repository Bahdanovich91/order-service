<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case InProcess = 'processing';
    case Pending   = 'pending';
    case Failed    = 'failed';
    case Completed = 'completed';
}
