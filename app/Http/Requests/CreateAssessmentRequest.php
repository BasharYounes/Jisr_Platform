<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'career_path_id' => ['required', 'integer', 'exists:career_paths,CareerPathID'],
            'cv_id' => [
                'nullable',
                'integer',
                Rule::exists('c_v_s', 'CvID')->where(
                    fn ($query) => $query->where('UserId', $this->user()->id)
                ),
            ],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ];
    }
}
