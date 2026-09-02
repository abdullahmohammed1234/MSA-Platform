<?php

namespace App\Ems\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends StoreTicketTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $event = $this->route('event');
        $eventId = $event?->id ?? $this->input('event_id');
        $ticketType = $this->route('ticketType');

        $rules['name'] = [
            'sometimes',
            'required',
            'string',
            'min:1',
            'max:120',
            Rule::unique('ems_ticket_types', 'name')
                ->where(fn ($query) => $query->where('event_id', $eventId)->whereNull('deleted_at'))
                ->ignore($ticketType?->id),
        ];
        $rules['price'] = ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'];

        return $rules;
    }
}
