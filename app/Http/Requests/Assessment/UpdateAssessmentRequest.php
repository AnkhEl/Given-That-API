<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
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
            'module_id' => ['sometimes', 'nullable', 'exists:course_modules,id'],
            'title' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'assessment_type' => ['sometimes', 'string', Rule::in(['quiz', 'project', 'coding_challenge', 'essay'])],
            'questions' => ['sometimes', 'array', 'min:1'],
            'questions.*.id' => ['required_with:questions', 'string'],
            'questions.*.type' => ['required_with:questions', 'string'],
            'questions.*.question' => ['required_with:questions', 'string'],
            'questions.*.points' => ['required_with:questions', 'integer', 'min:1'],
            'questions.*.correct_answer' => ['required_with:questions'],
            'questions.*.explanation' => ['nullable', 'string'],
            'passing_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'time_limit_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can update assessments.',
        ], 403));
    }
}
