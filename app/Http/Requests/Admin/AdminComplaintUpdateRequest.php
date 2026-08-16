<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminComplaintUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'under_review',
                    'resolved',
                    'rejected',
                ]),
            ],
            'resolution_notes' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('status'),
                        ['resolved', 'rejected'],
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
            'status.required' => 'حالة الشكوى مطلوبة. | Complaint status is required.',
            'status.string' => 'حالة الشكوى يجب أن تكون نصاً. | Complaint status must be a string.',
            'status.in' => 'يمكن للآدمن تغيير الحالة إلى قيد المراجعة أو محلولة أو مرفوضة فقط. | Admin can set the complaint status only to under_review, resolved, or rejected.',

            'resolution_notes.required' => 'ملاحظات المعالجة مطلوبة عند حل الشكوى أو رفضها. | Resolution notes are required when resolving or rejecting a complaint.',
            'resolution_notes.string' => 'ملاحظات المعالجة يجب أن تكون نصاً. | Resolution notes must be a string.',
            'resolution_notes.max' => 'ملاحظات المعالجة يجب ألا تتجاوز 5000 حرف. | Resolution notes must not exceed 5000 characters.',
        ];
    }
}
