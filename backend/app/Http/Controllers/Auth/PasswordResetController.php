<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    protected $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Send password reset request email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->input('email'));

        return response()->json([
            'message' => 'Check your email for reset instructions.',
        ]);
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword($request->validated());

        return response()->json([
            'message' => 'Password reset successful. You can now login.',
        ]);
    }
}
