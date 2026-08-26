<?php

namespace App\Dams;

use Illuminate\Support\ServiceProvider;

/**
 * DAMS (Dawah Academy Management System) application boundary.
 *
 * DAMS owns Academy administration operations over the shared Academy schema.
 * Identity/RBAC remain on the MSA Platform (Sanctum + shared permissions).
 * Learner runtime stays at /api/v1/academy/* and /academy.
 * Admin HTTP APIs remain at /api/v1/admin/academy/* for path stability.
 */
class DamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/dams.php', 'dams');
    }

    public function boot(): void
    {
        // Intentionally empty: DAMS HTTP surface stays on existing
        // /api/v1/admin/academy/* routes so clients remain compatible.
    }
}
