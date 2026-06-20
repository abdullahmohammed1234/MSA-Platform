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
