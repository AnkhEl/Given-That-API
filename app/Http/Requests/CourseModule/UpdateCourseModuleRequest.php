<?php

namespace App\Http\Requests\CourseModule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseModuleRequest extends FormRequest
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
        return [
            'parent_module_id' => ['sometimes', 'nullable', 'exists:course_modules,id'],
            'title' => ['sometimes', 'string', 'max:500'],
            'slug' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'order_index' => ['sometimes', 'integer', 'min:1'],
            'content_type' => ['sometimes', 'string', Rule::in(['text', 'video', 'interactive', 'quiz'])],
            'content' => ['nullable', 'string'],
            'comparison_points' => ['sometimes', 'array'],
            'comparison_points.*.source_concept' => ['required_with:comparison_points', 'string'],
            'comparison_points.*.target_concept' => ['required_with:comparison_points', 'string'],
            'comparison_points.*.explanation' => ['required_with:comparison_points', 'string'],
            'code_examples' => ['sometimes', 'array'],
            'resources' => ['sometimes', 'array'],
            'estimated_minutes' => ['sometimes', 'integer', 'min:1'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can update modules.',
        ], 403));
    }
}
