<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('company') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],

            'description' => ['sometimes', 'string'],

            'difficulty_level' => [
                'sometimes',
                'string',
                'in:beginner,intermediate,advanced',
            ],

            'duration_days' => [
                'sometimes',
                'integer',
                'min:1',
                'max:7',
            ],

            'deadline' => [
                'sometimes',
                'date',
                'after:now',
            ],

            'max_applicants' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'max_accepted_students' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'deliverables' => [
                'nullable',
                'array',
            ],

            'deliverables.*' => [
                'string',
                'max:255',
            ],

            'acceptance_criteria' => [
                'nullable',
                'array',
            ],

            'acceptance_criteria.*' => [
                'string',
                'max:500',
            ],

            'submission_type' => [
                'sometimes',
                'string',
                'in:github_link,zip_file,demo_link,mixed',
            ],

            'skills' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'skills.*.skill_id' => [
                'required_with:skills',
                'integer',
                'exists:skills,id',
            ],

            'skills.*.required_level' => [
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],

            'skills.*.weight' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'skills.*.mandatory' => [
                'nullable',
                'boolean',
            ],
        ];
    }


    public function messages(): array
{
    return [
        'title.string' => 'عنوان المهمة يجب أن يكون نصاً صحيحاً. | Task title must be a valid text.',
        'title.max' => 'عنوان المهمة يجب ألا يتجاوز 255 حرفاً. | Task title must not exceed 255 characters.',

        'description.string' => 'وصف المهمة يجب أن يكون نصاً صحيحاً. | Task description must be a valid text.',

        'difficulty_level.string' => 'مستوى الصعوبة يجب أن يكون نصاً صحيحاً. | Difficulty level must be a valid text.',
        'difficulty_level.in' => 'مستوى الصعوبة يجب أن يكون beginner أو intermediate أو advanced. | Difficulty level must be beginner, intermediate, or advanced.',

        'duration_days.integer' => 'مدة المهمة يجب أن تكون رقماً صحيحاً. | Task duration must be an integer.',
        'duration_days.min' => 'مدة المهمة يجب أن تكون يوماً واحداً على الأقل. | Task duration must be at least 1 day.',
        'duration_days.max' => 'مدة المهمة يجب ألا تتجاوز 7 أيام. | Task duration must not exceed 7 days.',

        'deadline.date' => 'موعد التسليم النهائي يجب أن يكون تاريخاً صحيحاً. | Task deadline must be a valid date.',
        'deadline.after' => 'موعد التسليم النهائي يجب أن يكون في المستقبل. | Task deadline must be in the future.',

        'max_applicants.integer' => 'الحد الأقصى للمتقدمين يجب أن يكون رقماً صحيحاً. | Maximum applicants must be an integer.',
        'max_applicants.min' => 'الحد الأقصى للمتقدمين يجب أن يكون 1 على الأقل. | Maximum applicants must be at least 1.',

        'max_accepted_students.integer' => 'عدد الطلاب المقبولين يجب أن يكون رقماً صحيحاً. | Maximum accepted students must be an integer.',
        'max_accepted_students.min' => 'عدد الطلاب المقبولين يجب أن يكون 1 على الأقل. | Maximum accepted students must be at least 1.',

        'deliverables.array' => 'المخرجات المطلوبة يجب أن تكون قائمة. | Deliverables must be an array.',
        'deliverables.*.string' => 'كل عنصر من المخرجات المطلوبة يجب أن يكون نصاً صحيحاً. | Each deliverable must be a valid text.',
        'deliverables.*.max' => 'كل عنصر من المخرجات المطلوبة يجب ألا يتجاوز 255 حرفاً. | Each deliverable must not exceed 255 characters.',

        'acceptance_criteria.array' => 'شروط قبول الحل يجب أن تكون قائمة. | Acceptance criteria must be an array.',
        'acceptance_criteria.*.string' => 'كل شرط قبول يجب أن يكون نصاً صحيحاً. | Each acceptance criterion must be a valid text.',
        'acceptance_criteria.*.max' => 'كل شرط قبول يجب ألا يتجاوز 500 حرف. | Each acceptance criterion must not exceed 500 characters.',

        'submission_type.string' => 'نوع التسليم يجب أن يكون نصاً صحيحاً. | Submission type must be a valid text.',
        'submission_type.in' => 'نوع التسليم يجب أن يكون github_link أو zip_file أو demo_link أو mixed. | Submission type must be github_link, zip_file, demo_link, or mixed.',

        'skills.array' => 'المهارات يجب أن تكون قائمة. | Skills must be an array.',
        'skills.min' => 'يجب اختيار مهارة واحدة على الأقل عند تعديل المهارات. | At least one skill must be selected when updating skills.',

        'skills.*.skill_id.required_with' => 'معرّف المهارة مطلوب عند إرسال قائمة المهارات. | Skill id is required when skills are provided.',
        'skills.*.skill_id.integer' => 'معرّف المهارة يجب أن يكون رقماً صحيحاً. | Skill id must be an integer.',
        'skills.*.skill_id.exists' => 'المهارة المختارة غير موجودة. | Selected skill does not exist.',

        'skills.*.required_level.integer' => 'المستوى المطلوب للمهارة يجب أن يكون رقماً صحيحاً. | Required skill level must be an integer.',
        'skills.*.required_level.min' => 'المستوى المطلوب للمهارة يجب أن يكون 1 على الأقل. | Required skill level must be at least 1.',
        'skills.*.required_level.max' => 'المستوى المطلوب للمهارة يجب ألا يتجاوز 5. | Required skill level must not exceed 5.',

        'skills.*.weight.numeric' => 'وزن المهارة يجب أن يكون رقماً. | Skill weight must be a number.',
        'skills.*.weight.min' => 'وزن المهارة يجب ألا يكون أقل من 0. | Skill weight must be at least 0.',
        'skills.*.weight.max' => 'وزن المهارة يجب ألا يتجاوز 100. | Skill weight must not exceed 100.',

        'skills.*.mandatory.boolean' => 'حقل mandatory يجب أن يكون true أو false. | Mandatory field must be true or false.',
    ];
}

}