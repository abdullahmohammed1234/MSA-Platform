<?php

namespace App\Spms\Services;

use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Spms\Models\Contact;
use App\Spms\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Find potential duplicate organizations based on legal name, display name, or email.
     */
    public function findDuplicates(string $name, ?string $email = null): Collection
    {
        $query = Organization::query();

        $likeName = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($name)) . '%';

        $query->where(function ($q) use ($likeName, $email) {
            $q->where('legal_name', 'like', $likeName)
              ->orWhere('display_name', 'like', $likeName);

            if (!empty($email)) {
                $q->orWhere('email', strtolower(trim($email)))
                  ->orWhereHas('contacts', function ($cq) use ($email) {
                      $cq->where('email', strtolower(trim($email)));
                  });
            }
        });

        return $query->get();
    }

    public function createOrganization(array $data, ?User $actor = null): Organization
    {
        return DB::transaction(function () use ($data, $actor) {
            $organization = Organization::create($data);

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.organization.created',
                    'spms_organization',
                    (string) $organization->id,
                    [
                        'legal_name' => $organization->legal_name,
                        'display_name' => $organization->display_name,
                        'relationship_type' => $organization->relationship_type?->value,
                    ],
                    $actor
                );
            }

            return $organization;
        });
    }

    public function updateOrganization(Organization $organization, array $data, ?User $actor = null): Organization
    {
        return DB::transaction(function () use ($organization, $data, $actor) {
            $organization->update($data);

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.organization.updated',
                    'spms_organization',
                    (string) $organization->id,
                    [
                        'legal_name' => $organization->legal_name,
                        'status' => $organization->status?->value,
                    ],
                    $actor
                );
            }

            return $organization;
        });
    }

    public function addContact(Organization $organization, array $data): Contact
    {
        return DB::transaction(function () use ($organization, $data) {
            if (!empty($data['is_primary']) && $data['is_primary'] === true) {
                Contact::where('organization_id', $organization->id)->update(['is_primary' => false]);
            }

            return $organization->contacts()->create($data);
        });
    }
}
