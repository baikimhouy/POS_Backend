<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct("Insufficient stock for variant {$variantId}: {$available} available, {$requested} requested.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient stock.',
            'errors' => ['stock' => [$this->getMessage()]],
        ], 409);
    }
}
