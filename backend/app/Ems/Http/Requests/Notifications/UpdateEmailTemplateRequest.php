<?php

namespace App\Ems\Http\Requests\Notifications;

use App\Ems\Http\Requests\EmsFormRequest;

class UpdateEmailTemplateRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'body_html' => ['sometimes', 'string', 'max:100000'],
            'body_text' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
