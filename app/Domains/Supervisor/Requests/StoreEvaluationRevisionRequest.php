<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * التفويض الحقيقي موجود في Policy.
         * هنا نتحقق فقط من شكل البيانات.
         */
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'bail',
                'required',
                'string',
                'min:10',
                'max:3000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' =>
                'The revision reason is required.',

            'reason.string' =>
                'The revision reason must be a valid text.',

            'reason.min' =>
                'The revision reason must contain at least 10 characters.',

            'reason.max' =>
                'The revision reason may not exceed 3000 characters.',
        ];
    }
}
