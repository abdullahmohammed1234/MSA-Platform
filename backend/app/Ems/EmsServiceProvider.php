<?php

namespace App\Ems;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Contracts\TicketIssuer;
use App\Ems\Listeners\EmsActivitySubscriber;
use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Models\EventTemplate;
use App\Ems\Models\EventSeries;
use App\Ems\Models\PromoCode;
use App\Ems\Models\EventFeedback;
use App\Ems\Policies\EventCategoryPolicy;
use App\Ems\Policies\EventPolicy;
use App\Ems\Policies\EventTemplatePolicy;
use App\Ems\Policies\EventSeriesPolicy;
use App\Ems\Policies\PromoCodePolicy;
use App\Ems\Policies\EventFeedbackPolicy;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Services\Ticketing\DefaultTicketIssuer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the EMS module into the host application.
 *
 * Everything the module needs to exist — routes, policies, its rate limiter
 * and its activity subscriber — is registered here, so adding the EMS to an
 * environment is a single provider entry and removing it leaves no traces
 * scattered across the platform's own bootstrap files.
 */
class EmsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Event::class => EventPolicy::class,
        EventCategory::class => EventCategoryPolicy::class,
        EventTemplate::class => EventTemplatePolicy::class,
        EventSeries::class => EventSeriesPolicy::class,
        PromoCode::class => PromoCodePolicy::class,
        EventFeedback::class => EventFeedbackPolicy::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/ems.php', 'ems');

        $this->app->singleton(TicketIssuer::class, DefaultTicketIssuer::class);
        $this->app->singleton(EventNotificationDispatcher::class, QueuedEventNotificationDispatcher::class);
        $this->app->singleton(\App\Ems\Services\Square\SquareClient::class);
        $this->app->singleton(\App\Ems\Services\Payments\Providers\SquarePaymentProvider::class);
        $this->app->singleton(\App\Ems\Services\Payments\PaymentProviderManager::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRateLimiter();
        $this->registerRoutes();
        $this->registerWebhookRoutes();
        $this->registerListeners();
    }

    private function registerPolicies(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * The EMS is an internal operations tool used in bursts (a list, then a
     * detail, then a transition), so it gets a more generous limit than the
     * platform's public forms but is still bounded per user.
     *
     * Public discovery and registration use tighter, IP-keyed limiters.
     */
    private function registerRateLimiter(): void
    {
        $name = (string) config('ems.route.throttle', 'ems_api');

        RateLimiter::for($name, function (Request $request): Limit {
            $perMinute = (int) config('ems.route.rate_limit_per_minute', 120);

            return Limit::perMinute($perMinute)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429, $headers));
        });

        $publicName = (string) config('ems.public.throttle', 'ems_public');

        RateLimiter::for($publicName, function (Request $request): Limit {
            $perMinute = (int) config('ems.public.rate_limit_per_minute', 60);

            return Limit::perMinute($perMinute)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                ], 429, $headers));
        });

        $registrationName = (string) config('ems.public.registration_throttle', 'ems_registration');

        RateLimiter::for($registrationName, function (Request $request): Limit {
            $perMinute = (int) config('ems.public.registration_rate_limit_per_minute', 5);

            return Limit::perMinute($perMinute)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many registration attempts. Please wait a moment and try again.',
                ], 429, $headers));
        });

        $webhookName = (string) config('ems.payments.webhook_throttle', 'ems_webhooks');

        RateLimiter::for($webhookName, function (Request $request): Limit {
            $perMinute = (int) config('ems.payments.webhook_rate_limit_per_minute', 120);

            return Limit::perMinute($perMinute)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many webhook requests.',
                ], 429, $headers));
        });

        $checkInName = (string) config('ems.operations.check_in_throttle', 'ems_check_in');

        RateLimiter::for($checkInName, function (Request $request): Limit {
            $perMinute = (int) config('ems.operations.check_in_rate_limit_per_minute', 60);

            return Limit::perMinute($perMinute)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => 'Too many check-in attempts. Please slow down.',
                ], 429, $headers));
        });
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix((string) config('ems.route.prefix', 'api/v1/ems'))
            ->middleware((array) config('ems.route.middleware', ['api']))
            ->name('api.ems.')
            ->group(__DIR__ . '/../../routes/ems.php');
    }

    /**
     * Square webhooks mount outside the EMS prefix so the notification URL
     * matches the documented /api/v1/webhooks/square contract.
     */
    private function registerWebhookRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware((array) config('ems.route.middleware', ['api']))
            ->prefix('api/v1/webhooks')
            ->name('api.webhooks.')
            ->group(function (): void {
                Route::post('/square', \App\Ems\Http\Controllers\V1\SquareWebhookController::class)
                    ->middleware('throttle:' . config('ems.payments.webhook_throttle', 'ems_webhooks'))
                    ->name('square');
            });
    }

    private function registerListeners(): void
    {
        EventFacade::subscribe(EmsActivitySubscriber::class);
    }
}
