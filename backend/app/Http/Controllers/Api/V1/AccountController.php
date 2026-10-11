<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Services\AccountSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    protected AccountSummaryService $summaryService;

    public function __construct(AccountSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    /**
     * GET /api/v1/account/summary
     * Get unified account summary for authenticated member.
     */
    public function summary(Request $request): JsonResponse
    {
        $data = $this->summaryService->getSummary($request->user());

        return response()->json([
            'message' => 'Account summary retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/v1/account/password
     * Update user password securely.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        // Revoke current token and generate fresh session token
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        $newToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Password updated successfully.',
            'token' => $newToken,
        ]);
    }
}
