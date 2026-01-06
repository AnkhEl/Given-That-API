<?php

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super_admin can update skills
        return $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $skillId = $this->route('skill');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('skills')->ignore($skillId)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('skills')->ignore($skillId)],
            'category' => ['sometimes', 'string', Rule::in([
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
            'popularity_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'metadata' => ['sometimes', 'array'],
            'metadata.related_skills' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This skill name is already taken',
            'slug.unique' => 'This skill slug is already taken',
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
            'message' => 'Unauthorized. Only super admins can update skills.',
        ], 403));
    }
}
