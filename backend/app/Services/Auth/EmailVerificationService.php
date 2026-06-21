<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    /**
     * Send email verification notification to the user.
     */
    public function sendVerification(User $user): void
    {
        // 1. Delete previous verification tokens
        DB::table('email_verifications')->where('email', $user->email)->delete();

        // 2. Create a new token
        $token = Str::random(40);
        $hashedToken = hash('sha256', $token);
        
        DB::table('email_verifications')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Send Notification
        $user->notify(new VerifyEmailNotification($token));
    }

    /**
     * Verify the email with the given token.
     */
    public function verify(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        
        $verification = DB::table('email_verifications')
            ->where('token', $hashedToken)
            ->first();

        if (!$verification) {
            throw ValidationException::withMessages([
                'token' => ['The verification token is invalid.'],
            ]);
        }

        if (now()->isAfter($verification->expires_at)) {
            DB::table('email_verifications')->where('token', $hashedToken)->delete();
            throw ValidationException::withMessages([
                'token' => ['The verification link has expired.'],
            ]);
        }

        $user = User::where('email', $verification->email)->first();
        if ($user) {
            $user->email_verified_at = now();
            $user->save();
        }

        // Clean up the verified token
        DB::table('email_verifications')->where('token', $hashedToken)->delete();

        return true;
    }

    /**
     * Resend verification email.
     */
    public function resend(User $user): void
    {
        if (!$user->requiresEmailVerification()) {
            throw ValidationException::withMessages([
                'email' => ['Email verification is not required for your account type.'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Your email is already verified.'],
            ]);
        }

        $this->sendVerification($user);
    }
}
