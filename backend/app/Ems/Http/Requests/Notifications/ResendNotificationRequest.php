<?php

namespace App\Ems\Http\Requests\Notifications;

use App\Ems\Enums\NotificationType;
use App\Ems\Http\Requests\EmsFormRequest;
use Illuminate\Validation\Rule;

class ResendNotificationRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $resendable = array_map(fn (NotificationType $t) => $t->value, NotificationType::resendable());

        return [
            'type' => ['required', 'string', Rule::in($resendable)],
            'registration_uuid' => ['required', 'uuid', 'exists:ems_registrations,uuid'],
        ];
    }
}
