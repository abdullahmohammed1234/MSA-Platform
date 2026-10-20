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
    public function login(string $email, string $password, ?\Illuminate\Http\Request $request = null): array
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
                'email' => ['Volunteers must sign in with an @sfu.ca email address.'],
            ]);
        }

        // Staff accounts never go through public email verification.
        $user->markEmailVerifiedIfStaff();

        // Update last login timestamp
        $user->last_login_at = now();
        $user->save();

        $user->load(['roles.permissions', 'permissions']);

        // Initiate web session and regenerate session ID if request has an active session
        if ($request && $request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        // Generate Sanctum token for backward compatibility with API/token clients
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Log user out by deleting active Sanctum token and invalidating web session.
     */
    public function logout(?User $user, ?\Illuminate\Http\Request $request = null): void
    {
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            } elseif ($token && !($token instanceof \Laravel\Sanctum\TransientToken) && method_exists($token, 'delete')) {
                $token->delete();
            } elseif ($request?->bearerToken()) {
                $this->revokePresentedToken($user, $request);
            }
        }

        // Invalidate shared web session if present
        if ($request && $request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function revokePresentedToken(User $user, ?\Illuminate\Http\Request $request): void
    {
        $bearer = $request?->bearerToken();
        if (!$bearer) {
            return;
        }

        $presented = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);

        if ($presented
            && $presented->tokenable_type === $user->getMorphClass()
            && (string) $presented->tokenable_id === (string) $user->getKey()) {
            $presented->delete();
        }
    }

    private function isSfuEmail(string $email): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._%+-]+@(?:[a-zA-Z0-9-]+\.)*sfu\.ca$/i', $email);
    }

    private function canUseNonSfuEmail(User $user): bool
    {
        if ($user->hasAnyRole(User::STAFF_ROLES) || $user->hasRole('member')) {
            return true;
        }

        if ($user->hasRole('volunteer')) {
            return false;
        }

        return true;
    }
}
