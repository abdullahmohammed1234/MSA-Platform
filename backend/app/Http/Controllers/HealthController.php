<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;

/**
 * Uptime probe at /up. Same checks and status codes as the framework's
 * health route, but a JSON body: no third-party script, no timing, and no
 * exception text in the response.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            Event::dispatch(new DiagnosingHealth);
        } catch (\Throwable $e) {
            if (app()->hasDebugModeEnabled()) {
                throw $e;
            }

            report($e);

            return response()->json(['status' => 'down'], 500);
        }

        return response()->json(['status' => 'up']);
    }
}
