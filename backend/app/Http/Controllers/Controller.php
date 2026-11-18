<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }

    protected function respond(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
