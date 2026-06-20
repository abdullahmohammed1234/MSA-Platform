<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$request->user()->id,
        ]);

        $this->userService->updateProfile($request->user(), $validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }

    public function completeAcademyOnboarding(Request $request): JsonResponse
    {
        $this->userService->completeAcademyOnboarding($request->user());

        return response()->json([
            'message' => 'Academy onboarding completed.',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }
}
