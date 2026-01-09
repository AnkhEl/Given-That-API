<?php

namespace App\Http\Requests\SkillMetricFile;

use Illuminate\Foundation\Http\FormRequest;

class UploadSkillMetricFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Company admins and super admins can upload
        return $this->user()->hasAnyRole(['super_admin', 'company_admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx',
                'max:10240', // 10MB max
            ],
            'trainee_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'timeline_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'special_requirements' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a file',
            'file.mimes' => 'File must be PDF, Word (DOC/DOCX), or Excel (XLS/XLSX)',
            'file.max' => 'File size must not exceed 10MB',
            'trainee_count.min' => 'Trainee count must be at least 1',
            'trainee_count.max' => 'Trainee count cannot exceed 1000',
            'timeline_weeks.min' => 'Timeline must be at least 1 week',
            'timeline_weeks.max' => 'Timeline cannot exceed 52 weeks (1 year)',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only company administrators can upload skill metric files.',
        ], 403));
    }
}
