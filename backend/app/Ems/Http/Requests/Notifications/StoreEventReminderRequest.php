<?php

namespace App\Ems\Http\Requests\Notifications;

use App\Ems\Enums\ReminderAudience;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Http\Requests\EmsFormRequest;
use Illuminate\Validation\Rule;

class StoreEventReminderRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'offset_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'offset_unit' => ['required', 'string', Rule::in(ReminderOffsetUnit::values())],
            'enabled' => ['sometimes', 'boolean'],
            'template_key' => ['sometimes', 'string', 'max:64'],
            'audience' => ['sometimes', 'string', Rule::in(ReminderAudience::values())],
        ];
    }
}
