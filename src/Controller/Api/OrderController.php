<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\OrderAction;
use App\Factory\CreateOrderDtoFactory;
use App\Handler\Order\OrderHandler;
use App\Response\OrderResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders', name: 'orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly CreateOrderDtoFactory $dtoFactory,
        private readonly OrderHandler $orderHandler,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $orders = ($this->orderHandler)(OrderAction::GetAll);
        } catch (\Throwable $error) {
            return OrderResponse::error($error);
        }

        return OrderResponse::collection($orders);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto   = $this->dtoFactory->fromRequest($request);
            $order = ($this->orderHandler)(OrderAction::Create, $dto);
        } catch (\Throwable $error) {
            return OrderResponse::error($error);
        }

        return OrderResponse::item($order, Response::HTTP_CREATED);
    }
}
