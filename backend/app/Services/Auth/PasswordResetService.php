<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    /**
     * Send password reset link to user.
     */
    public function sendResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['We could not find a user with that email address.'],
            ]);
        }

        // Delete old password reset tokens
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Generate token
        $token = Str::random(64);

        // Store token
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token), // Hash token for database security
            'created_at' => now(),
        ]);

        // Send reset email with plain token
        $user->notify(new ResetPasswordNotification($token, $email));
    }

    /**
     * Reset user password using token.
     */
    public function resetPassword(array $data): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        // Check if token matches
        if (!Hash::check($data['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        // Check if expired (60 minutes)
        if (now()->subMinutes(60)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token has expired.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();
        if ($user) {
            $user->password = Hash::make($data['password']);
            $user->save();

            // Clear tokens
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            
            // Delete all personal access tokens to force relogging
            $user->tokens()->delete();
        }

        return true;
    }
}
