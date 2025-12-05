<?php

declare(strict_types=1);

namespace App\Controller;

use App\Factory\CreateOrderDtoFactory;
use App\Response\OrderResponse;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/orders', name: 'orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CreateOrderDtoFactory $dtoFactory,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto   = $this->dtoFactory->fromRequest($request);
            $order = $this->orderService->createOrder($dto);

            return OrderResponse::item($order, Response::HTTP_CREATED);

        } catch (\Throwable $error) {
            return OrderResponse::error($error);
        }
    }
}
