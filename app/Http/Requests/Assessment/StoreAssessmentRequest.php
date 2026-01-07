<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
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
            'module_id' => ['nullable', 'exists:course_modules,id'],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'assessment_type' => ['required', 'string', Rule::in(['quiz', 'project', 'coding_challenge', 'essay'])],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', Rule::in(['multiple_choice', 'multiple_select', 'true_false', 'coding_challenge'])],
            'questions.*.question' => ['required', 'string'],
            'questions.*.points' => ['required', 'integer', 'min:1'],
            'questions.*.correct_answer' => ['required'],
            'questions.*.explanation' => ['nullable', 'string'],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'is_required' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Assessment title is required',
            'assessment_type.required' => 'Assessment type is required',
            'questions.required' => 'At least one question is required',
            'passing_score.required' => 'Passing score is required',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only curators and admins can create assessments.',
        ], 403));
    }
}
