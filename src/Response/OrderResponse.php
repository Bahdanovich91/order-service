<?php

declare(strict_types=1);

namespace App\Response;

use App\Exception\InsufficientBalanceException;
use App\Exception\InsufficientInventoryException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Order;
use Throwable;

final class OrderResponse
{
    public static function item(mixed $item, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'item'    => self::normalize($item),
        ], $status);
    }

    private static function normalize(Order $data): mixed
    {
        return [
            'order_id'     => $data->getId(),
            'user_id'      => $data->getUserId(),
            'status'       => $data->getStatus(),
            'total_amount' => $data->getTotalAmount(),
            'created_at'   => $data->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    public static function error(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof InsufficientInventoryException => new JsonResponse([
                'success' => false,
                'error'   => 'Insufficient inventory',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST),

            $e instanceof InsufficientBalanceException => new JsonResponse([
                'success' => false,
                'error'   => 'Insufficient balance',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST),

            default => new JsonResponse([
                'success' => false,
                'error'   => 'Internal server error',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR),
        };
    }
}
