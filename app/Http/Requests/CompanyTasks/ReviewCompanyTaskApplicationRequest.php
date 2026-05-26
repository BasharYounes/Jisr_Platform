<?php

namespace App\Http\Requests\CompanyTasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewCompanyTaskApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_notes' => ['nullable', 'string', 'max:1000'], 
        ];
    }

     public function messages(): array
    {
         return [
            'company_notes.string' => [
                'ar' => 'ملاحظات الشركة يجب أن تكون نصاً.',
                'en' => 'Company notes must be a string.',   
            ],
        
            'company_notes.max' => [
                'ar' => 'ملاحظات الشركة يجب ألا تتجاوز 1000 حرف.',
                'en' => 'Company notes must not exceed 1000 characters.',
            ],
        ];
    }
}
