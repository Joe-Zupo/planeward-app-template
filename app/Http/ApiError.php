<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;

/** `{ "error": "..." }` responses used by the API routes. Never cached. */
class ApiError
{
    public static function make(int $status, string $error, array $headers = []): JsonResponse
    {
        return response()->json(['error' => $error], $status, [
            'Cache-Control' => 'no-store',
            ...$headers,
        ]);
    }

    public static function unauthorized(string $error): JsonResponse
    {
        return self::make(401, $error, ['WWW-Authenticate' => 'Bearer']);
    }
}
