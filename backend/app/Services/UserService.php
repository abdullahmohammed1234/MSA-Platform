<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function updateProfile(User $user, array $data): bool
    {
        // A new address has not been proven yet; guest records keyed by email
        // must not attach to this account until it is verified again.
        if (array_key_exists('email', $data) && strcasecmp((string) $data['email'], (string) $user->email) !== 0) {
            $user->forceFill(['email_verified_at' => null]);
        }

        return $this->userRepository->update($user, $data);
    }

    public function completeAcademyOnboarding(User $user): bool
    {
        if ($user->academy_onboarding_completed_at) {
            return true;
        }

        return $this->userRepository->update($user, [
            'academy_onboarding_completed_at' => now(),
        ]);
    }
}
