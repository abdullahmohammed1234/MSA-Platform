<?php

namespace App\Cms;

use Illuminate\Support\ServiceProvider;

/**
 * CMS application boundary registration.
 *
 * CMS owns homepage, announcements, team, resources, media, and revisions.
 * Identity/RBAC remain on the MSA Platform (Sanctum + shared permissions).
 * Academy/course assets must not use CMS MediaController — see AcademyAssetService.
 *
 * Admin CMS HTTP routes currently remain under routes/api.php at
 * /api/v1/admin/cms/* for backward-compatible path stability. This provider
 * marks CMS as a first-class module (parallel to App\Ems) and loads CMS config.
 */
class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/cms.php', 'cms');
    }

    public function boot(): void
    {
        // Intentionally empty: CMS HTTP surface stays on existing /api/v1/admin/cms/*
        // and /api/v1/website/* paths so Main Website / Academy consumers are unchanged.
    }
}
