<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Role;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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
     *
     * @return array{user: User, verification_email_sent: bool}
     */
    public function register(array $data): array
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'gender' => $data['gender'] ?? null,
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
        ];

        $user = $this->userRepository->create($userData);

        $role = Role::where('slug', $data['role'])->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        // The account is already committed; a mail outage must not turn it into an error.
        // The user recovers through the authenticated resend-verification endpoint.
        try {
            $this->emailVerificationService->sendVerification($user);
            $sent = true;
        } catch (Throwable $exception) {
            Log::error('Verification email could not be sent after registration', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
            $sent = false;
        }

        return ['user' => $user, 'verification_email_sent' => $sent];
    }
}
