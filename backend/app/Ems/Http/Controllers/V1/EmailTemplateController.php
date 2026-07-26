<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Notifications\UpdateEmailTemplateRequest;
use App\Ems\Http\Resources\EmailTemplateResource;
use App\Ems\Models\EmailTemplate;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailTemplateController extends EmsController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(EmsPermissions::TEMPLATES_VIEW), 403);

        $templates = EmailTemplate::query()->orderBy('category')->orderBy('name')->get();

        return ApiResponse::success(
            EmailTemplateResource::collection($templates),
            'Email templates retrieved.'
        );
    }

    public function show(Request $request, EmailTemplate $template): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(EmsPermissions::TEMPLATES_VIEW), 403);

        return ApiResponse::success(new EmailTemplateResource($template), 'Email template retrieved.');
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(EmsPermissions::TEMPLATES_MANAGE), 403);

        $template->fill($request->validated());
        $template->updated_by = $request->user()?->id;
        $template->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.templates.updated', [
                'template_key' => $template->key,
                'actor_id' => $request->user()?->id,
            ]);

        return ApiResponse::success(
            new EmailTemplateResource($template->fresh()),
            'Email template updated.'
        );
    }
}
