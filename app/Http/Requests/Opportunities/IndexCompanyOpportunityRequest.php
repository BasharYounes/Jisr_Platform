<?php

namespace App\Http\Requests\Opportunities;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCompanyOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(Opportunity::STATUSES),
            ],

            'type' => [
                'nullable',
                'string',
                Rule::in(Opportunity::TYPES),
            ],

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
