<?php

namespace App\Ems\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single JSON contract for every EMS endpoint.
 *
 * Success:  { "success": true,  "message": "...", "data": {...}, "meta": {...} }
 * Failure:  { "success": false, "message": "...", "errors": {...} }
 */
final class ApiResponse
{
    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK.',
        array $meta = [],
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::resolve($data),
            'meta' => (object) $meta,
        ], $status);
    }

    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    public static function created(mixed $data = null, string $message = 'Created.', array $meta = []): JsonResponse
    {
        return self::success($data, $message, $meta, Response::HTTP_CREATED);
    }

    /**
     * A response with no body payload, e.g. after a delete.
     */
    public static function deleted(string $message = 'Deleted.'): JsonResponse
    {
        return self::success(null, $message);
    }

    /**
     * Wrap a paginated result, hoisting pagination details into `meta` so the
     * `data` key is always the collection itself.
     *
     * @param  array<string, mixed>  $extraMeta
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'OK.',
        ?string $resourceClass = null,
        array $extraMeta = []
    ): JsonResponse {
        $items = $resourceClass
            ? $resourceClass::collection($paginator->getCollection())
            : $paginator->getCollection();

        return self::success($items, $message, array_merge([
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $extraMeta));
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validationFailed(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return self::error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function unauthenticated(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_NOT_FOUND);
    }

    /**
     * State/business-rule conflicts, e.g. an illegal lifecycle transition or
     * deleting a category that still has events attached.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function conflict(string $message, array $errors = []): JsonResponse
    {
        return self::error($message, $errors, Response::HTTP_CONFLICT);
    }

    public static function serverError(string $message = 'An unexpected error occurred. Please try again.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Normalise resources so `data` is never `null` when a collection is empty
     * and never double-wrapped in the framework's own `data` key.
     */
    private static function resolve(mixed $data): mixed
    {
        if ($data instanceof ResourceCollection || $data instanceof JsonResource) {
            return $data->resolve();
        }

        return $data;
    }
}
