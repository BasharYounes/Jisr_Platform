<?php

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleOpportunityInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => [
                'required',
                'date',
                'after:now',
            ],
            'meeting_type' => [
                'sometimes',
                'string',
                'in:online,onsite,phone',
            ],
            'meeting_link' => [
                'nullable',
                'url',
                'required_if:meeting_type,online',
            ],
            'location' => [
                'nullable',
                'string',
                'max:255',
                'required_if:meeting_type,onsite',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}