<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super_admin', 'curator']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $courseId = $this->route('course');

        return [
            'source_skill_id' => ['sometimes', 'exists:skills,id'],
            'target_skill_id' => ['sometimes', 'exists:skills,id'],
            'title' => ['sometimes', 'string', 'max:500'],
            'slug' => ['sometimes', 'string', 'max:500', Rule::unique('courses')->ignore($courseId)],
            'description' => ['nullable', 'string'],
            'learning_objectives' => ['nullable', 'string'],
            'prerequisites' => ['nullable', 'string'],
            'difficulty_level' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'estimated_hours' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'ai_generated', 'in_curation', 'published', 'archived'])],
            'ai_generated_outline' => ['sometimes', 'array'],
            'curated_by' => ['sometimes', 'exists:users,id'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'tags' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can update courses.',
        ], 403));
    }
}
