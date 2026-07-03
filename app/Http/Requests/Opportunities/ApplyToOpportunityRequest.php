<?php

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;

class ApplyToOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv_id' => [
                'nullable',
                'integer',
                'exists:c_v_s,CvID',
            ],
            'cover_letter' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cv_id.exists' => 'السيرة الذاتية المختارة غير موجودة. | Selected CV does not exist.',
            'cover_letter.string' => 'رسالة التقديم يجب أن تكون نصًا. | Cover letter must be a string.',
            'cover_letter.max' => 'رسالة التقديم طويلة جدًا. | Cover letter is too long.',
        ];
    }
}
