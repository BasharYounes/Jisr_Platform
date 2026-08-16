<?php

namespace App\Http\Requests\Complaints;

use App\Enums\ComplaintContextType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'student',
            'company',
            'supervisor',
        ]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge([
                'reason' => trim($this->input('reason')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'context_type' => [
                'required',
                'string',
                Rule::enum(ComplaintContextType::class),
            ],
            'context_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'target_user_id' => [
                Rule::prohibitedIf(
                    fn (): bool => $this->input('context_type')
                        !== ComplaintContextType::ProjectAssignment->value
                ),
                'nullable',
                'integer',
                'min:1',
            ],
            'complainant_user_id' => ['prohibited'],
            'reported_user_id' => ['prohibited'],
            'reported_mentor_profile_id' => ['prohibited'],
            'status' => ['prohibited'],
            'resolution_notes' => ['prohibited'],
            'deduplication_key' => ['prohibited'],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'context_type.required' => 'سياق الشكوى مطلوب. | Complaint context is required.',
            'context_type.enum' => 'سياق الشكوى غير مدعوم. | The selected complaint context is not supported.',
            'context_id.required' => 'معرّف السياق مطلوب. | Context ID is required.',
            'context_id.integer' => 'معرّف السياق يجب أن يكون عدداً صحيحاً. | Context ID must be an integer.',
            'context_id.min' => 'معرّف السياق غير صالح. | Context ID is invalid.',
            'target_user_id.prohibited' => 'يمكن استخدام target_user_id فقط ضمن سياق project_assignment. | target_user_id may only be used with the project_assignment context.',
            'target_user_id.integer' => 'معرّف المستخدم المستهدف يجب أن يكون عدداً صحيحاً. | Target user ID must be an integer.',
            'target_user_id.min' => 'معرّف المستخدم المستهدف غير صالح. | Target user ID is invalid.',
            'complainant_user_id.prohibited' => 'لا يمكن للعميل تحديد صاحب الشكوى. | The client may not set the complainant user.',
            'reported_user_id.prohibited' => 'لا يمكن للعميل تحديد المستخدم المشتكى عليه مباشرة. | The client may not set reported_user_id directly.',
            'reported_mentor_profile_id.prohibited' => 'لا يمكن للعميل تحديد المرشد المشتكى عليه مباشرة. | The client may not set reported_mentor_profile_id directly.',
            'status.prohibited' => 'لا يمكن للعميل تحديد حالة الشكوى عند الإنشاء. | The client may not set complaint status on creation.',
            'resolution_notes.prohibited' => 'ملاحظات المعالجة خاصة بالآدمن. | Resolution notes are admin-only.',
            'deduplication_key.prohibited' => 'مفتاح منع التكرار يُنشأ داخلياً. | Deduplication key is generated internally.',
            'reason.required' => 'سبب الشكوى مطلوب. | Complaint reason is required.',
            'reason.string' => 'سبب الشكوى يجب أن يكون نصاً. | Complaint reason must be a string.',
            'reason.min' => 'سبب الشكوى يجب أن يحتوي على 10 أحرف على الأقل. | Complaint reason must be at least 10 characters.',
            'reason.max' => 'سبب الشكوى يجب ألا يتجاوز 5000 حرف. | Complaint reason must not exceed 5000 characters.',
        ];
    }
}
