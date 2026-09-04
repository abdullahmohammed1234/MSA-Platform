<?php

namespace App\Spms\Support;

final class SpmsRoles
{
    public const SUPER_ADMIN = 'super-admin';
    public const SPMS_ADMINISTRATOR = 'spms-administrator';
    public const SPMS_STAFF = 'spms-staff';
    public const SPMS_VIEWER = 'spms-viewer';

    /**
     * @return array<int, array{slug: string, name: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => self::SPMS_ADMINISTRATOR,
                'name' => 'Sponsorship Administrator',
                'description' => 'Full administrative management of corporate partnerships, agreements, commitments, and financial reporting.',
            ],
            [
                'slug' => self::SPMS_STAFF,
                'name' => 'Sponsorship Staff',
                'description' => 'Manages partner communications, organization contacts, follow-up logs, and deliverable fulfillment.',
            ],
            [
                'slug' => self::SPMS_VIEWER,
                'name' => 'Sponsorship Viewer',
                'description' => 'Read-only access to SPMS dashboard, partner rosters, and sponsorship fulfillment progress.',
            ],
        ];
    }

    /**
     * Permission grants per SPMS role.
     *
     * @return array<string, array<int, string>>
     */
    public static function permissionMatrix(): array
    {
        return [
            self::SUPER_ADMIN => SpmsPermissions::all(),

            self::SPMS_ADMINISTRATOR => SpmsPermissions::all(),

            self::SPMS_STAFF => [
                SpmsPermissions::SPONSORSHIP_VIEW,
                SpmsPermissions::SPONSORSHIP_CREATE,
                SpmsPermissions::SPONSORSHIP_EDIT,
                SpmsPermissions::SPONSORSHIP_FULFILLMENT,
            ],

            self::SPMS_VIEWER => [
                SpmsPermissions::SPONSORSHIP_VIEW,
            ],
        ];
    }
}
