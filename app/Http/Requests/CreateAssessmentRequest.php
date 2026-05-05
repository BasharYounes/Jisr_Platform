<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'cv_id' => ['nullable', 'integer', 'exists:c_v_s,CvID'],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ];
    }
}
