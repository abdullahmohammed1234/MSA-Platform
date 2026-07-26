<?php

namespace App\Ems\Http\Requests\Notifications;

use App\Ems\Enums\ReminderAudience;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Http\Requests\EmsFormRequest;
use Illuminate\Validation\Rule;

class UpdateEventReminderRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'offset_value' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'offset_unit' => ['sometimes', 'string', Rule::in(ReminderOffsetUnit::values())],
            'enabled' => ['sometimes', 'boolean'],
            'template_key' => ['sometimes', 'string', 'max:64'],
            'audience' => ['sometimes', 'string', Rule::in(ReminderAudience::values())],
        ];
    }
}
