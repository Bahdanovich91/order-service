<?php

declare(strict_types=1);

namespace App\Dto;

readonly class CreateOrderDto
{
    public function __construct(
        public int   $userId,
        public array $items
    ) {
    }
}
