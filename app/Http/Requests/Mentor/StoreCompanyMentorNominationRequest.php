<?php

namespace App\Http\Requests\Mentor;

use App\Enums\MentoringTopic;
use App\Enums\SupervisorSpecialization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyMentorNominationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('company') ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:254',
            ],
            'specialization' => [
                'required',
                Rule::enum(SupervisorSpecialization::class),
            ],
            'professional_title' => [
                'required',
                'string',
                'max:255',
            ],
            'expertise' => [
                'required',
                'string',
                'max:5000',
            ],
            'bio' => [
                'required',
                'string',
                'max:3000',
            ],
            'linkedin_url' => [
                'required',
                'url:http,https',
                'max:2048',
            ],
            'github_or_portfolio_url' => [
                'required',
                'url:http,https',
                'max:2048',
            ],
            'whatsapp_number' => [
                'required',
                'string',
                'max:50',
            ],
            'cv' => [
                'required',
                'file',
                'mimes:pdf,docx',
                'max:5120',
            ],
            'mentoring_topics' => [
                'required',
                'array',
                'min:1',
            ],
            'mentoring_topics.*' => [
                'required',
                'distinct',
                Rule::enum(MentoringTopic::class),
            ],
        ];
    }
}
