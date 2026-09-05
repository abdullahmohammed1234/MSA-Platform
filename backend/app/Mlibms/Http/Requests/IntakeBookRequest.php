<?php

namespace App\Mlibms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IntakeBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'isbn_10' => 'nullable|string|max:20',
            'isbn_13' => 'nullable|string|max:20',
            'primary_category_id' => 'nullable|exists:mlibms_categories,id',
            'publisher_name' => 'nullable|string|max:255',
            'publisher_id' => 'nullable|exists:mlibms_publishers,id',
            'author_names' => 'nullable|array',
            'author_names.*' => 'string|max:255',
            'edition' => 'nullable|string|max:50',
            'publication_year' => 'nullable|integer|min:1000|max:2100',
            'language' => 'nullable|string|max:50',
            'summary' => 'nullable|string',
            'cover_image_url' => 'nullable|url|max:500',
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'is_reference_only' => 'nullable|boolean',
            'copies' => 'nullable|array',
            'copies.*.location_id' => 'nullable|exists:mlibms_locations,id',
            'copies.*.condition' => 'nullable|string|in:new,good,fair,worn,damaged',
            'copies.*.acquisition_cost_cents' => 'nullable|integer|min:0',
            'copies.*.replacement_cost_cents' => 'nullable|integer|min:0',
            'copies.*.notes' => 'nullable|string',
        ];
    }
}
