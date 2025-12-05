<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateOrderDto;
use App\Exception\InsufficientBalanceException;
use App\Exception\InsufficientInventoryException;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/orders', name: 'orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id']) || !isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(
                ['error' => 'Invalid request. Required fields: user_id, items'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $constraints = new Assert\Collection([
            'user_id' => [new Assert\Type('integer'), new Assert\Positive()],
            'items' => [
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\Collection([
                        'product_id' => [new Assert\Type('integer'), new Assert\Positive()],
                        'warehouse_id' => [new Assert\Type('integer'), new Assert\Positive()],
                        'quantity' => [new Assert\Type('integer'), new Assert\Positive()],
                    ]),
                ]),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            return new JsonResponse(
                ['error' => 'Validation failed', 'details' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $dto = new CreateOrderDto(
                (int) $data['user_id'],
                $data['items']
            );

            $order = $this->orderService->createOrder($dto);

            return new JsonResponse(
                [
                    'success' => true,
                    'order_id' => $order->getId(),
                    'user_id' => $order->getUserId(),
                    'status' => $order->getStatus(),
                    'total_amount' => $order->getTotalAmount(),
                    'message' => 'Order created. Processing through Kafka...',
                ],
                Response::HTTP_CREATED
            );
        } catch (InsufficientInventoryException $e) {
            return new JsonResponse(
                ['error' => 'Insufficient inventory', 'message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (InsufficientBalanceException $e) {
            return new JsonResponse(
                ['error' => 'Insufficient balance', 'message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Internal server error', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
