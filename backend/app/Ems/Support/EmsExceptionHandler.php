<?php

namespace App\Ems\Support;

use App\Ems\Exceptions\EmsException;
use App\Ems\Services\EmsActivityLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Centralised error handling for the EMS API.
 *
 * Every renderer is gated on handles(), so this only ever changes responses
 * for EMS routes and leaves the rest of the MSA platform's error behaviour
 * exactly as it was.
 *
 * The contract: clients always receive the ApiResponse error envelope, and
 * production responses never carry an exception class, file, line or stack
 * trace. Details go to the logs instead.
 */
final class EmsExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            return ApiResponse::validationFailed($e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            return ApiResponse::unauthenticated('Unauthenticated. Sign in to continue.');
        });

        // Laravel's handler runs prepareException() before these callbacks, so
        // an AuthorizationException thrown by a policy arrives here already
        // wrapped as AccessDeniedHttpException.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            self::logger()?->denied('request.forbidden', null, 'Authorization denied.', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            $message = $e->getMessage();

            return ApiResponse::forbidden(
                $message !== '' && $message !== 'This action is unauthorized.'
                    ? $message
                    : 'You do not have permission to perform this action.'
            );
        });

        // Route-model binding failures arrive wrapped the same way.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                return ApiResponse::notFound(sprintf(
                    'The requested %s was not found.',
                    self::humanise($previous->getModel())
                ));
            }

            return ApiResponse::notFound('The requested endpoint was not found.');
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            return ApiResponse::error('Too many requests. Please slow down.', [], 429);
        });

        // EMS domain failures carry a client-safe message and their own status.
        $exceptions->render(function (EmsException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            return ApiResponse::error($e->getMessage(), $e->errors(), $e->status());
        });

        // Database failures must never surface SQL or connection details.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            self::logger()?->error('EMS database error.', [
                'path' => $request->path(),
                'method' => $request->method(),
                'exception' => $e::class,
                'sql_state' => $e->getCode(),
            ]);

            return config('app.debug')
                ? ApiResponse::error('Database error: ' . $e->getMessage(), [], 500)
                : ApiResponse::serverError('A database error occurred. Please try again.');
        });

        // Catch-all. Anything already carrying an HTTP status keeps it.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::handles($request)) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                    [],
                    $e->getStatusCode()
                );
            }

            self::logger()?->error('Unhandled EMS exception.', [
                'path' => $request->path(),
                'method' => $request->method(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return config('app.debug')
                ? ApiResponse::error($e->getMessage(), ['exception' => [$e::class]], 500)
                : ApiResponse::serverError();
        });
    }

    /**
     * "App\Ems\Models\EventCategory" -> "event category".
     */
    private static function humanise(?string $model): string
    {
        if ($model === null) {
            return 'resource';
        }

        return strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($model))));
    }

    /**
     * Whether the request targets the EMS API (including Square webhooks).
     */
    public static function handles(Request $request): bool
    {
        $prefix = trim((string) config('ems.route.prefix', 'api/v1/ems'), '/');

        if ($request->is($prefix, $prefix . '/*')) {
            return true;
        }

        return $request->is('api/v1/webhooks/square', 'api/v1/webhooks/square/*');
    }

    /**
     * Resolved lazily and defensively: error handling must not itself fail if
     * the container is mid-teardown.
     */
    private static function logger(): ?EmsActivityLogger
    {
        try {
            return app(EmsActivityLogger::class);
        } catch (Throwable) {
            return null;
        }
    }
}
