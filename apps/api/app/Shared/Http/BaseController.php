<?php

namespace App\Shared\Http;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Return a standardized success response with meta.request_id.
     */
    protected function success(mixed $data = null, int $status = 200, string $message = 'OK'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'request_id' => $this->requestId(),
            ],
        ], $status);
    }

    /**
     * Return a standardized created response.
     */
    protected function created(mixed $data = null): JsonResponse
    {
        return $this->success($data, 201);
    }

    /**
     * Return a standardized no-content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a standardized error response.
     */
    protected function error(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => [
                'request_id' => $this->requestId(),
            ],
        ], $status);
    }

    /**
     * Return a standardized paginated response with meta.
     */
    protected function paginated(AnonymousResourceCollection $collection): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
            'success' => true,
            'data' => $collection->resolve(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => $this->requestId(),
            ],
        ]);
    }

    /**
     * Generate a unique request ID.
     */
    private function requestId(): string
    {
        return 'req_' . Str::random(12);
    }
}
