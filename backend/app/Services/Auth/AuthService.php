<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Authenticate user credentials and return user and token.
     */
    public function login(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        $isSfuEmail = $this->isSfuEmail($email);

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            event(new \Illuminate\Auth\Events\Failed('api', $user, ['email' => $email]));
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        if (!$isSfuEmail && !$this->canUseNonSfuEmail($user)) {
            throw ValidationException::withMessages([
                'email' => ['Members and volunteers must sign in with an @sfu.ca email address.'],
            ]);
        }

        // Staff accounts never go through public email verification.
        $user->markEmailVerifiedIfStaff();

        // Update last login timestamp
        $user->last_login_at = now();
        $user->save();

        $user->load(['roles.permissions', 'permissions']);

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Log user out by deleting their active Sanctum token.
     */
    public function logout(User $user): void
    {
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } else {
            $user->tokens()->delete();
        }
    }

    private function isSfuEmail(string $email): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._%+-]+@(?:[a-zA-Z0-9-]+\.)*sfu\.ca$/i', $email);
    }

    private function canUseNonSfuEmail(User $user): bool
    {
        return $user->hasAnyRole(User::STAFF_ROLES);
    }
}
