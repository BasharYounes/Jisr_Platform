<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProjectEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'general_comment' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.evaluation_criteria_id' => [
                'required',
                'exists:evaluation_criteria,id',
            ],

            'items.*.score' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.comment' => [
                'nullable',
                'string',
                'max:1500',
            ],

            /*
             * شرح نصي اختياري من المشرف.
             */
            'items.*.evidence' => [
                'nullable',
                'string',
                'max:3000',
            ],

            /*
             * صورة واحدة على الأقل لكل معيار،
             * وحتى خمس صور للمعيار الواحد.
             */
            'items.*.evidence_images' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'items.*.evidence_images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}
