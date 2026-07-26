<?php

namespace App\Ems\Http\Requests;

use App\Ems\Enums\EventTransition;
use Illuminate\Validation\Rule;

/**
 * Validates a lifecycle transition request.
 *
 * Only the action name is accepted — the target state is derived from the
 * state machine, so a client can never name a destination directly.
 */
class TransitionEventRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(array_column(EventTransition::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'A lifecycle action is required.',
            'action.in' => 'That is not a recognised lifecycle action.',
        ];
    }

    public function transition(): EventTransition
    {
        return EventTransition::from($this->validated()['action']);
    }
}
