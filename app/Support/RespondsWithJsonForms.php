<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithJsonForms
{
    protected function wantsFormJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function jsonOk(Request $request, string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => true,
            'message' => $message,
        ], $extra), $status);
    }

    protected function jsonRedirect(Request $request, string $url, string $message = ''): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'redirect' => $url,
        ]);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    protected function jsonError(Request $request, string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
