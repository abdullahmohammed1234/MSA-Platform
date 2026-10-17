<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    protected $registrationService;

    public function __construct(RegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * Handle incoming user registration request.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registrationService->register($request->validated());
        $user = $result['user'];
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => $result['verification_email_sent']
                ? 'Registration successful.'
                : 'Registration successful, but we could not send the verification email. Please use "Resend verification email" to try again.',
            'user' => new UserResource($user),
            'token' => $token,
            'verification_email_sent' => $result['verification_email_sent'],
        ], 201);
    }
}
