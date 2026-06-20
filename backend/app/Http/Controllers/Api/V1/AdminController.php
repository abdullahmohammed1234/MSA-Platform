<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function users(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $users = \App\Models\User::with(['roles', 'permissions'])->get();

        $transformed = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->toArray(),
                'permissions' => $user->permissions->pluck('slug')->toArray(),
            ];
        });

        return response()->json(['users' => $transformed]);
    }

    public function createCourse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'boolean',
        ]);

        $course = $this->courseService->createCourse($validated);

        return response()->json([
            'message' => 'Course created successfully.',
            'course' => $course,
        ], 201);
    }

    public function updateCourse(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Course updated successfully.']);
    }

    public function deleteCourse(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Course deleted successfully.']);
    }
}
