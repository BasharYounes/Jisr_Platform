<?php

namespace App\Http\Requests\Opportunities;

use App\Models\OpportunityInterview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStudentOpportunityInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => [
                'nullable',
                'string',
                Rule::in([
                    'upcoming',
                    'history',
                ]),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(OpportunityInterview::STATUSES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'filter.string' => 'فلتر المقابلات يجب أن يكون نصاً. | Interview filter must be a string.',
            'filter.in' => 'فلتر المقابلات غير صالح. القيم المسموحة: upcoming, history. | Invalid interview filter.',
            'status.string' => 'حالة المقابلة يجب أن تكون نصاً. | Interview status must be a string.',
            'status.in' => 'حالة المقابلة غير صالحة. | The selected interview status is invalid.',
        ];
    }
}
