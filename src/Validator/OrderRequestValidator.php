<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class OrderRequestValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    public function validate(array $data): void
    {
        $constraints = new Assert\Collection([
            'user_id' => [new Assert\Type('integer'), new Assert\Positive()],
            'items'   => [
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\Collection([
                        'product_id'   => [new Assert\Type('integer'), new Assert\Positive()],
                        'warehouse_id' => [new Assert\Type('integer'), new Assert\Positive()],
                        'quantity'     => [new Assert\Type('integer'), new Assert\Positive()],
                    ])
                ])
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            throw new BadRequestHttpException(json_encode($errors));
        }
    }
}
