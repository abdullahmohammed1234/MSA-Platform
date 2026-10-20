<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Support\AuthorizationMemo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Under Option A (Shared-Session SSO), requests originating from approved
        // browser frontend origins authenticate strictly via shared web sessions.
        // Personal access tokens cannot be used to bypass an invalidated or missing session
        // from a stateful browser origin. Non-browser API clients continue to authenticate statelessly.
        \Laravel\Sanctum\Sanctum::getAccessTokenFromRequestUsing(function (\Illuminate\Http\Request $request): ?string {
            if (\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
                return null;
            }

            $token = $request->bearerToken();
            if (! $token) {
                return null;
            }

            if (str_contains($token, '|')) {
                [$id, $secret] = explode('|', $token, 2);
                if (! ctype_digit($id) || empty($secret)) {
                    return null;
                }
            }

            return $token;
        });

        \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) {
            $this->app->make(\App\Support\AuthorizationMemo::class)->observeStatement($query->sql);
        });

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    app(\App\Services\Security\SecurityLogger::class)->log(
                        'rate_limit_violation',
                        'low',
                        'Authentication rate limit exceeded on endpoint: ' . $request->path(),
                        $request->except(['password', 'password_confirmation', 'token'])
                    );
                    return response()->json(['message' => 'Too many attempts. Please try again later.'], 429, $headers);
                });
        });

        \Illuminate\Support\Facades\RateLimiter::for('public_forms', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    app(\App\Services\Security\SecurityLogger::class)->log(
                        'rate_limit_violation',
                        'low',
                        'Public form submission rate limit exceeded on endpoint: ' . $request->path(),
                        $request->except(['password', 'password_confirmation', 'token'])
                    );
                    return response()->json(['message' => 'Too many requests. Please try again later.'], 429, $headers);
                });
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin_api', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $key = $user ? $user->id : $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by($key)
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    app(\App\Services\Security\SecurityLogger::class)->log(
                        'rate_limit_violation',
                        'medium',
                        'Admin API rate limit exceeded by ' . ($request->user() ? 'User ID: ' . $request->user()->id : 'IP: ' . $request->ip()),
                        $request->except(['password', 'password_confirmation', 'token'])
                    );
                    return response()->json(['message' => 'Rate limit exceeded. Please slow down.'], 429, $headers);
                });
        });

        \Illuminate\Support\Facades\RateLimiter::for('dm_send', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $key = $user ? $user->id : $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($key);
        });

        \Illuminate\Support\Facades\RateLimiter::for('dm_create', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $key = $user ? $user->id : $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($key);
        });

        \Illuminate\Support\Facades\RateLimiter::for('dm_search', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $key = $user ? $user->id : $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($key);
        });

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability, $arguments = []) {
            // Do not bypass conversation participant checks for admins
            if (str_starts_with($ability, 'conversation.') || (isset($arguments[0]) && $arguments[0] instanceof \App\Models\Conversation)) {
                return null;
            }

            if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
                return true;
            }
        });

        \Illuminate\Support\Facades\Gate::policy(\App\Models\SupportInquiry::class, \App\Policies\SupportInquiryPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Conversation::class, \App\Policies\ConversationPolicy::class);

        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\NotificationEventSubscriber::class);

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskFailed::class,
            \App\Listeners\ScheduledTaskFailureListener::class
        );

        // Listen for Authentication Events to write to security/audit logs
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            function (\Illuminate\Auth\Events\Failed $event) {
                app(\App\Services\Security\SecurityLogger::class)->log(
                    'failed_login',
                    'medium',
                    'Failed login attempt for user: ' . ($event->credentials['email'] ?? 'unknown'),
                    ['email' => $event->credentials['email'] ?? 'unknown'],
                    $event->user ? $event->user->id : null
                );
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                app(\App\Services\Security\AuditLogger::class)->log(
                    'user_login',
                    $event->user,
                    'User logged in successfully.',
                    [],
                    $event->user->id
                );
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function (\Illuminate\Auth\Events\Logout $event) {
                if ($event->user) {
                    app(\App\Services\Security\AuditLogger::class)->log(
                        'user_logout',
                        $event->user,
                        'User logged out.',
                        [],
                        $event->user->id
                    );
                }
            }
        );
    }
}
