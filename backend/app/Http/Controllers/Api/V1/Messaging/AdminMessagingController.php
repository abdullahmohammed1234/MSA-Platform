<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMessagingController extends Controller
{
    /**
     * Search eligible recipients (either 'members' or 'admins') for initiating administrative conversations.
     */
    public function recipients(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!DirectMessagingAuthorizationService::isAdmin($user)) {
            return response()->json([
                'message' => 'Unauthorized. Administrative direct messaging access is required.',
            ], 403);
        }

        $type = $request->input('type', 'members');
        if (!in_array($type, ['members', 'admins'])) {
            return response()->json([
                'message' => "Invalid recipient type. Must be 'members' or 'admins'.",
            ], 422);
        }

        $search = $request->input('search');
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $results = DirectMessagingAuthorizationService::searchRecipients($user, $type, $search, $perPage);

        $results->getCollection()->transform(function (User $recipient) {
            $isAdmin = DirectMessagingAuthorizationService::isAdmin($recipient);
            $roleLabel = $isAdmin
                ? ($recipient->roles->first()?->name ?? 'MSA Administrator')
                : 'Member';

            return [
                'id' => $recipient->id,
                'uuid' => $recipient->uuid,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'avatar' => $recipient->avatar,
                'role_label' => $roleLabel,
                'is_admin' => $isAdmin,
            ];
        });

        return response()->json($results);
    }
}
