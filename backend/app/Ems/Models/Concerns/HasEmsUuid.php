<?php

namespace App\Ems\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a UUID on create and makes it the route key.
 *
 * EMS resources are addressed by UUID in the API so sequential integer ids are
 * never exposed to clients, matching the convention the CMS module already
 * uses for its public-facing routes.
 */
trait HasEmsUuid
{
    protected static function bootHasEmsUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
