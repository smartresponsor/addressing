<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Responder;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Factory\AddressViewArrayFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds Addressing HTTP JSON responses from canonical view data and error conditions.
 */
final readonly class AddressResponder
{
    public function __construct(private AddressViewArrayFactory $addressViewArrayFactory)
    {
    }

    /** Returns the canonical JSON representation for one address aggregate. */
    public function address(AddressInterface $address): JsonResponse
    {
        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /**
     * Wraps summary rows in the standard collection response envelope.
     *
     * @param array<int|string, mixed> $summary
     */
    public function summaryItems(array $summary): JsonResponse
    {
        return new JsonResponse(['items' => $summary]);
    }

    /** Returns the standard not-found response used by Addressing HTTP entry points. */
    public function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
    }

    /** Converts a request exception into the canonical client-error JSON response. */
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
