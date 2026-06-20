<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\User;

class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_certificate_templates');
    }

    public function view(User $user, CertificateTemplate $template): bool
    {
        return $user->hasPermission('manage_certificate_templates');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_certificate_templates');
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return $user->hasPermission('manage_certificate_templates');
    }

    public function delete(User $user, CertificateTemplate $template): bool
    {
        return $user->hasPermission('manage_certificate_templates');
    }
}
