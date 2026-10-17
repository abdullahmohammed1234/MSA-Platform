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
     * Shown for every syntactically valid forgot-password request.
     * Known and unknown addresses must produce this same message.
     */
    public const GENERIC_RESET_REQUEST_MESSAGE = 'If an account matches that email address, password reset instructions will be sent.';

    /**
     * Accept a reset request without revealing whether the address is registered.
     *
     * Mail is queued only for a matching account. A missing account, or a
     * failure while queueing mail, still returns normally so the HTTP response
     * stays identical. This does not make response timing identical: a matching
     * account performs extra writes and queues a notification.
     */
    public function sendResetLink(string $email): void
    {
        $user = $this->findUserByEmail($email);

        if (!$user) {
            return;
        }

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        try {
            $user->notify(new ResetPasswordNotification($token, $user->email));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Reset user password using token.
     */
    public function resetPassword(array $data): bool
    {
        $email = strtolower(trim((string) $data['email']));

        $record = DB::table('password_reset_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        if (!Hash::check($data['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        if (now()->subMinutes(60)->gt($record->created_at)) {
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token has expired.'],
            ]);
        }

        $user = $this->findUserByEmail($record->email);
        if (!$user) {
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $record->email)->delete();
        $user->tokens()->delete();

        return true;
    }

    private function findUserByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return User::whereRaw('LOWER(email) = ?', [$normalized])->first();
    }
}
