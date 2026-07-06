<?php

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleOpportunityInterviewRequest extends FormRequest
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
                'required',
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

    public function messages(): array
    {
        return [
            'scheduled_at.required' => 'موعد المقابلة مطلوب. | Interview date is required.',
            'scheduled_at.after' => 'موعد المقابلة يجب أن يكون في المستقبل. | Interview date must be in the future.',
            'meeting_type.required' => 'نوع المقابلة مطلوب. | Meeting type is required.',
            'meeting_type.in' => 'نوع المقابلة غير مدعوم. | Unsupported meeting type.',
            'meeting_link.required_if' => 'رابط الاجتماع مطلوب للمقابلة الأونلاين. | Meeting link is required for online interviews.',
            'meeting_link.url' => 'رابط الاجتماع غير صالح. | Meeting link must be a valid URL.',
            'location.required_if' => 'الموقع مطلوب للمقابلة الحضورية. | Location is required for onsite interviews.',
        ];
    }
}
