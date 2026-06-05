<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\EntityInterface\Record\AddressInterface;
use App\Http\Factory\AddressViewArrayFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class AddressHttpResponderService
{
    public function __construct(private AddressViewArrayFactory $addressViewArrayFactory)
    {
    }

    public function address(AddressInterface $address): JsonResponse
    {
        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /** @param array<int|string, mixed> $summary */
    public function summaryItems(array $summary): JsonResponse
    {
        return new JsonResponse(['items' => $summary]);
    }

    public function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
    }

    public function invalidRequest(
        \Throwable $exception,
        string $error = 'invalid_request',
        int $status = Response::HTTP_BAD_REQUEST,
    ): JsonResponse {
        return new JsonResponse([
            'error' => $error,
            'message' => $exception->getMessage(),
        ], $status);
    }
}
