<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Role;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationService
{
    protected $userRepository;
    protected $emailVerificationService;

    public function __construct(
        UserRepository $userRepository,
        EmailVerificationService $emailVerificationService
    ) {
        $this->userRepository = $userRepository;
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Register a new member or volunteer account from the public website.
     */
    public function register(array $data): User
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
            'email_verified_at' => now(),
        ];

        $user = $this->userRepository->create($userData);

        $role = Role::where('slug', $data['role'])->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        $this->emailVerificationService->sendVerification($user);

        return $user;
    }
}
