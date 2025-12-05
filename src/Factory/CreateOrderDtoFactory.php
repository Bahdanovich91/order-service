<?php

declare(strict_types=1);

namespace App\Factory;

use App\Dto\CreateOrderDto;
use App\Validator\OrderRequestValidator;
use Symfony\Component\HttpFoundation\Request;

final class CreateOrderDtoFactory
{
    public function __construct(
        private readonly OrderRequestValidator $validator
    ) {}

    public function fromRequest(Request $request): CreateOrderDto
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $this->validator->validate($data);

        return new CreateOrderDto(
            (int) $data['user_id'],
            $data['items']
        );
    }
}
