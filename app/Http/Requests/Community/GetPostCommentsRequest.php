<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class GetPostCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string', 'in:latest,oldest,top'],
        ];
    }

    public function messages(): array
    {
        return [
            'filter.in' => 'نوع الفلتر غير صحيح. الخيارات المتاحة: latest, oldest, top.',
        ];
    }
}
