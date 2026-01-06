<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only curators and super admins can create courses
        return $this->user()->hasAnyRole(['super_admin', 'curator']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'skill_metric_file_id' => ['nullable', 'exists:skill_metric_files,id'],
            'source_skill_id' => ['nullable', 'exists:skills,id'],
            'target_skill_id' => ['nullable', 'exists:skills,id'],
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:500', 'unique:courses,slug'],
            'description' => ['nullable', 'string'],
            'learning_objectives' => ['nullable', 'string'],
            'prerequisites' => ['nullable', 'string'],
            'difficulty_level' => ['required', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'estimated_hours' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'ai_generated', 'in_curation', 'published', 'archived'])],
            'ai_generated_outline' => ['nullable', 'array'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'tags' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Course title is required',
            'difficulty_level.required' => 'Difficulty level is required',
            'difficulty_level.in' => 'Invalid difficulty level',
            'status.in' => 'Invalid status',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can create courses.',
        ], 403));
    }
}
