<?php

namespace App\Spms\Support;

final class SpmsPermissions
{
    public const MODULE = 'Sponsorship';

    public const SPONSORSHIP_VIEW = 'sponsorship.view';
    public const SPONSORSHIP_CREATE = 'sponsorship.create';
    public const SPONSORSHIP_EDIT = 'sponsorship.edit';
    public const SPONSORSHIP_MANAGE = 'sponsorship.manage';
    public const SPONSORSHIP_AGREEMENTS = 'sponsorship.agreements';
    public const SPONSORSHIP_PAYMENTS = 'sponsorship.payments';
    public const SPONSORSHIP_FULFILLMENT = 'sponsorship.fulfillment';
    public const SPONSORSHIP_EXPORT = 'sponsorship.export';
    public const SPONSORSHIP_ADMIN = 'sponsorship.admin';

    /**
     * @return array<string, array{name: string, module: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            self::SPONSORSHIP_VIEW => [
                'name' => 'View Sponsorships',
                'module' => self::MODULE,
                'description' => 'View SPMS dashboard, corporate partners, opportunities, and sponsorship records.',
            ],
            self::SPONSORSHIP_CREATE => [
                'name' => 'Create Sponsorship Opportunities',
                'module' => self::MODULE,
                'description' => 'Register new partner organizations, contacts, and sponsorship opportunity packages.',
            ],
            self::SPONSORSHIP_EDIT => [
                'name' => 'Edit Sponsorship Details',
                'module' => self::MODULE,
                'description' => 'Update partner organization info, contacts, follow-ups, and opportunity details.',
            ],
            self::SPONSORSHIP_MANAGE => [
                'name' => 'Manage Sponsorships',
                'module' => self::MODULE,
                'description' => 'Full administrative management of corporate partnerships, commitments, and status transitions.',
            ],
            self::SPONSORSHIP_AGREEMENTS => [
                'name' => 'Manage Sponsorship Agreements',
                'module' => self::MODULE,
                'description' => 'Execute, upload, and update formal sponsorship legal agreements.',
            ],
            self::SPONSORSHIP_PAYMENTS => [
                'name' => 'Manage Sponsorship Payments',
                'module' => self::MODULE,
                'description' => 'Record manual cash/cheque payments and create Square checkout links for commitments.',
            ],
            self::SPONSORSHIP_FULFILLMENT => [
                'name' => 'Manage Deliverables & Fulfillment',
                'module' => self::MODULE,
                'description' => 'Track benefit deliverables, in-kind contributions, and mark sponsorship fulfillment complete.',
            ],
            self::SPONSORSHIP_EXPORT => [
                'name' => 'Export Sponsorship Reports',
                'module' => self::MODULE,
                'description' => 'Export SPMS financial reports, renewals, and partner logs in CSV format.',
            ],
            self::SPONSORSHIP_ADMIN => [
                'name' => 'SPMS Administrator',
                'module' => self::MODULE,
                'description' => 'Full administrative control over Sponsorship & Partnerships Management System.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }
}
