<?php

namespace App\Http\Requests\CourseModule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseModuleRequest extends FormRequest
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
            'parent_module_id' => ['nullable', 'exists:course_modules,id'],
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'order_index' => ['required', 'integer', 'min:1'],
            'content_type' => ['required', 'string', Rule::in(['text', 'video', 'interactive', 'quiz'])],
            'content' => ['nullable', 'string'],
            'comparison_points' => ['nullable', 'array'],
            'comparison_points.*.source_concept' => ['required_with:comparison_points', 'string'],
            'comparison_points.*.target_concept' => ['required_with:comparison_points', 'string'],
            'comparison_points.*.explanation' => ['required_with:comparison_points', 'string'],
            'code_examples' => ['nullable', 'array'],
            'resources' => ['nullable', 'array'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Module title is required',
            'slug.required' => 'Module slug is required',
            'order_index.required' => 'Order index is required',
            'content_type.required' => 'Content type is required',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can create modules.',
        ], 403));
    }
}
