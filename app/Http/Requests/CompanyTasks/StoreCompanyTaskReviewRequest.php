<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyTaskReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quality_score' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'commitment_score' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'communication_score' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'final_decision' => [
                'required',
                Rule::in([
                    'approved',
                    'needs_changes',
                    'rejected',
                ]),
            ],

            'feedback' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('final_decision'),
                        ['needs_changes', 'rejected'],
                        true
                    )
                ),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'quality_score.required' => 'درجة جودة العمل مطلوبة. | Quality score is required.',

            'quality_score.integer' => 'درجة جودة العمل يجب أن تكون رقمًا صحيحًا. | Quality score must be an integer.',

            'quality_score.between' => 'درجة جودة العمل يجب أن تكون بين 0 و100. | Quality score must be between 0 and 100.',

            'commitment_score.required' => 'درجة الالتزام مطلوبة. | Commitment score is required.',

            'commitment_score.integer' => 'درجة الالتزام يجب أن تكون رقمًا صحيحًا. | Commitment score must be an integer.',

            'commitment_score.between' => 'درجة الالتزام يجب أن تكون بين 0 و100. | Commitment score must be between 0 and 100.',

            'communication_score.required' => 'درجة التواصل مطلوبة. | Communication score is required.',

            'communication_score.integer' => 'درجة التواصل يجب أن تكون رقمًا صحيحًا. | Communication score must be an integer.',

            'communication_score.between' => 'درجة التواصل يجب أن تكون بين 0 و100. | Communication score must be between 0 and 100.',

            'final_decision.required' => 'قرار المراجعة النهائي مطلوب. | Final review decision is required.',

            'final_decision.in' => 'قرار المراجعة غير صالح. | The final review decision is invalid.',

            'feedback.required' => 'ملاحظات الشركة مطلوبة عند طلب تعديلات أو رفض التسليم. | Feedback is required when requesting changes or rejecting the submission.',

            'feedback.string' => 'ملاحظات الشركة يجب أن تكون نصًا. | Feedback must be a string.',

            'feedback.max' => 'ملاحظات الشركة يجب ألا تتجاوز 5000 حرف. | Feedback must not exceed 5000 characters.',
        ];
    }
}
