<?php

namespace App\Domains\Supervisor\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'general_comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'items' => [
                'sometimes',
                'array',
                'min:1',
            ],

            /*
             * نعدل العنصر الموجود نفسه.
             * لا نسمح بتغيير المعيار المرتبط به.
             */
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                'exists:evaluation_items,id',
            ],

            'items.*.score' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:1500',
            ],

            'items.*.evidence' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            if (
                ! $this->has('general_comment')
                && ! $this->has('items')
            ) {
                $validator->errors()->add(
                    'evaluation',
                    'At least general_comment or items must be provided.'
                );
            }
        });
    }
}
