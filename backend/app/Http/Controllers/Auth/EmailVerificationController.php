<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    protected $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Verify the user email with verification token.
     */
    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $this->emailVerificationService->verify($request->input('token'));

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    /**
     * Resend verification email link.
     */
    public function resend(Request $request): JsonResponse
    {
        $this->emailVerificationService->resend($request->user());

        return response()->json([
            'message' => 'Verification email resent.',
        ]);
    }
}
