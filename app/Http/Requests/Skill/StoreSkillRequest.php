<?php

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super_admin can create skills
        return $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:skills,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:skills,slug'],
            'category' => ['required', 'string', Rule::in([
                'programming_language',
                'framework',
                'database',
                'devops',
                'cloud',
                'frontend',
                'backend',
                'mobile',
                'cms',
                'testing',
                'other',
            ])],
            'description' => ['nullable', 'string'],
            'icon_url' => ['nullable', 'url', 'max:500'],
            'popularity_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'metadata.related_skills' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Skill name is required',
            'name.unique' => 'This skill already exists',
            'category.required' => 'Category is required',
            'category.in' => 'Invalid category selected',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only super admins can create skills.',
        ], 403));
    }
}
