<?php

namespace App\Services;

use App\Models\User;
use App\Models\ApplicationAccess;
use App\Services\Security\AuditLogger;
use App\Support\AuthorizationMemo;
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
        'volunteering',
        'vms',
        'connect',
        'msa-connect',
        'admin-portal',
        'projects',
        'pms',
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

        // Check if there is an explicit record in the table first (check aliases)
        $appsToCheck = match ($application) {
            'vms', 'volunteering' => ['vms', 'volunteering'],
            'projects', 'pms' => ['projects', 'pms'],
            'connect', 'msa-connect' => ['connect', 'msa-connect'],
            default => [$application],
        };
        if ($this->hasExplicitGrant($user, $appsToCheck)) {
            return true;
        }

        // If no explicit record, check dynamic fallback logic (backward compatibility)
        if ($application === 'dawah-academy') {
            if ($user->hasAnyRole(['volunteer', 'mentor'])) {
                return true;
            }
        }

        if ($application === 'store') {
            if ($user->hasAnyRole(['store-administrator', 'store-staff']) || $this->hasPermissionSafe($user, 'store.view') || $this->hasPermissionSafe($user, 'store.products.view')) {
                return true;
            }
        }

        if ($application === 'donations') {
            if ($user->hasAnyRole(['dms-administrator', 'dms-staff']) || $this->hasPermissionSafe($user, 'donations.view') || $this->hasPermissionSafe($user, 'donations.manage')) {
                return true;
            }
        }

        if ($application === 'sponsorship') {
            if ($user->hasAnyRole(['spms-administrator', 'spms-staff', 'spms-viewer']) || $this->hasPermissionSafe($user, 'sponsorship.view') || $this->hasPermissionSafe($user, 'sponsorship.manage')) {
                return true;
            }
        }

        if ($application === 'mlibms') {
            if ($user->hasAnyRole(['mlibms-administrator', 'mlibms-cataloger', 'mlibms-viewer', 'library-admin', 'library-staff']) || $this->hasPermissionSafe($user, 'library.view') || $this->hasPermissionSafe($user, 'library.catalog')) {
                return true;
            }
        }

        // 'volunteer' is self-service at registration, so it is deliberately
        // excluded: this application is the volunteering administration API.
        if ($application === 'volunteering' || $application === 'vms') {
            if ($user->hasAnyRole(['vms-administrator', 'vms-manager', 'volunteer-administrator', 'volunteer-coordinator', 'ems-administrator']) || $this->hasPermissionSafe($user, 'vms.view') || $this->hasPermissionSafe($user, 'vms.manage') || $this->hasPermissionSafe($user, 'volunteer.registrations.view') || $this->hasPermissionSafe($user, 'volunteer.registrations.update')) {
                return true;
            }
        }

        if ($application === 'connect' || $application === 'msa-connect') {
            if ($user->hasAnyRole(['connect-administrator', 'connect-staff', 'member']) || $this->hasPermissionSafe($user, 'connect.view') || $this->hasPermissionSafe($user, 'connect.manage')) {
                return true;
            }
        }

        // 'projects' / 'pms' requires an explicit application-access grant in the application_access table.
        // Formally appointed team members, department members, leads, and volunteers cannot enter PMS
        // without an explicit grant (or privileged super-admin status).

        return false;
    }

    /**
     * @param  list<string>  $applications
     */
    private function hasExplicitGrant(User $user, array $applications): bool
    {
        $memo = app(AuthorizationMemo::class);
        if ($memo->active() && $user->exists) {
            return $memo->hasApplicationGrant($user, $applications);
        }

        return $user->applicationAccess()
            ->whereIn('application', $applications)
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->exists();
    }

    private function hasPermissionSafe(User $user, string $permission): bool
    {
        try {
            return $user->can($permission) || $user->hasPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
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
                } elseif ($this->hasExplicitGrant($user, [$app])) {
                    $source = 'explicit';
                } else {
                    $source = 'role';
                }
            } else {
                $source = 'none';
            }

            $grant = $user->applicationAccess()->where('application', $app)->first();
            $status = $grant ? ($grant->status ?? 'active') : ($access ? 'active' : 'none');

            $result[$app] = [
                'access' => $access,
                'status' => $status,
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

        $access = $user->applicationAccess()->firstOrNew([
            'application' => $application
        ]);

        $wasNew = !$access->exists;
        $wasSuspended = $access->status === 'suspended';

        $access->status = 'active';
        $access->granted_by = $grantedBy?->id ?? Auth::id();
        $access->save();

        if ($wasNew || $wasSuspended) {
            $this->auditLogger->log(
                $wasSuspended ? 'restore_application_access' : 'grant_application_access',
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
     * Suspend explicit application access.
     */
    public function suspend(User $user, string $application, ?User $suspendedBy = null, ?string $reason = null): void
    {
        if (!in_array($application, self::APPLICATIONS, true)) {
            throw new \InvalidArgumentException("Invalid application identifier: {$application}");
        }

        $access = $user->applicationAccess()->where('application', $application)->first();
        if ($access) {
            $access->status = 'suspended';
            $access->save();

            $this->auditLogger->log(
                'suspend_application_access',
                $user,
                "Suspended access to application: {$application} for user: {$user->email}",
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'application' => $application,
                    'suspended_by' => $suspendedBy?->id ?? Auth::id(),
                    'reason' => $reason,
                ],
                $suspendedBy?->id ?? Auth::id()
            );
        }
    }

    /**
     * Restore suspended application access.
     */
    public function restore(User $user, string $application, ?User $restoredBy = null): void
    {
        $this->grant($user, $application, $restoredBy);
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
