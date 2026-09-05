<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApplicationAccess;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\Auth;

class ApplicationAccessService
{
    private const PRIVILEGED_ROLES = ['super-admin', 'admin'];

    private const APPLICATIONS = [
        'main-website',
        'cms',
        'dawah-academy',
        'dams',
        'ems',
        'store',
        'donations',
        'sponsorship',
        'mlibms',
        'admin-portal'
    ];


    protected $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Determine if the user has access to the application.
     */
    public function canAccess(User $user, string $application): bool
    {
        if (!in_array($application, self::APPLICATIONS, true)) {
            return false;
        }

        // Privileged administrators bypass application access for everything
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        // Check if there is an explicit record in the table first
        $explicitRecord = $user->applicationAccess()->where('application', $application)->first();
        if ($explicitRecord !== null) {
            return true;
        }

        // If no explicit record, check dynamic fallback logic (backward compatibility)
        if ($application === 'dawah-academy') {
            if ($user->hasAnyRole(['volunteer', 'mentor'])) {
                return true;
            }
        }

        if ($application === 'store') {
            if ($user->hasAnyRole(['store-administrator', 'store-staff']) || $user->hasPermissionTo('store.view') || $user->hasPermissionTo('store.products.view')) {
                return true;
            }
        }

        if ($application === 'donations') {
            if ($user->hasAnyRole(['dms-administrator', 'dms-staff']) || $user->hasPermissionTo('donations.view') || $user->hasPermissionTo('donations.manage')) {
                return true;
            }
        }

        if ($application === 'sponsorship') {
            if ($user->hasAnyRole(['spms-administrator', 'spms-staff', 'spms-viewer']) || $user->hasPermissionTo('sponsorship.view') || $user->hasPermissionTo('sponsorship.manage')) {
                return true;
            }
        }

        if ($application === 'mlibms') {
            if ($user->hasAnyRole(['mlibms-administrator', 'mlibms-cataloger', 'mlibms-viewer', 'library-admin', 'library-staff']) || $user->hasPermissionTo('library.view') || $user->hasPermissionTo('library.catalog')) {
                return true;
            }
        }

        return false;

    }

    /**
     * Get access details for all applications for the given user.
     */
    public function accessibleApplications(User $user): array
    {
        $result = [];

        foreach (self::APPLICATIONS as $app) {
            $access = $this->canAccess($user, $app);
            
            if ($access) {
                if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
                    $source = 'privileged';
                } elseif ($user->applicationAccess()->where('application', $app)->exists()) {
                    $source = 'explicit';
                } else {
                    $source = 'role';
                }
            } else {
                $source = 'none';
            }

            $result[$app] = [
                'access' => $access,
                'source' => $source
            ];
        }

        return $result;
    }

    /**
     * Grant explicit application access.
     */
    public function grant(User $user, string $application, ?User $grantedBy = null): void
    {
        if (!in_array($application, self::APPLICATIONS, true)) {
            throw new \InvalidArgumentException("Invalid application identifier: {$application}");
        }

        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return;
        }

        $access = $user->applicationAccess()->firstOrCreate([
            'application' => $application
        ], [
            'granted_by' => $grantedBy?->id
        ]);

        if ($access->wasRecentlyCreated) {
            $this->auditLogger->log(
                'grant_application_access',
                $user,
                "Granted access to application: {$application} for user: {$user->email}",
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'application' => $application,
                    'granted_by' => $grantedBy?->id ?? Auth::id()
                ],
                $grantedBy?->id ?? Auth::id()
            );
        }
    }

    /**
     * Revoke explicit application access.
     */
    public function revoke(User $user, string $application, ?User $revokedBy = null): void
    {
        if (!in_array($application, self::APPLICATIONS, true)) {
            throw new \InvalidArgumentException("Invalid application identifier: {$application}");
        }

        $deleted = $user->applicationAccess()->where('application', $application)->delete();

        if ($deleted > 0) {
            $this->auditLogger->log(
                'revoke_application_access',
                $user,
                "Revoked access to application: {$application} for user: {$user->email}",
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'application' => $application,
                    'revoked_by' => $revokedBy?->id ?? Auth::id()
                ],
                $revokedBy?->id ?? Auth::id()
            );
        }
    }
}
