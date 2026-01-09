<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillMetricFile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'uploaded_by',
        'original_filename',
        'file_path',
        'file_size',
        'file_type',
        'status',
        'processed_at',
        'error_message',
        'extracted_data',
        'source_skills',
        'target_skills',
        'trainee_count',
        'timeline_weeks',
        'special_requirements',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'processed_at' => 'datetime',
        'extracted_data' => 'array',
        'source_skills' => 'array',
        'target_skills' => 'array',
        'file_size' => 'integer',
        'trainee_count' => 'integer',
        'timeline_weeks' => 'integer',
    ];

    /**
     * Get the company that owns the file.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who uploaded the file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get courses generated from this file.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'skill_metric_file_id');
    }

    /**
     * Check if file processing is complete.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if file processing failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if file is currently being processed.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Mark file as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    /**
     * Mark file as completed.
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'extracted_data' => $data['extracted_data'] ?? $this->extracted_data,
            'source_skills' => $data['source_skills'] ?? $this->source_skills,
            'target_skills' => $data['target_skills'] ?? $this->target_skills,
        ]);
    }

    /**
     * Mark file as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Scope to get files by company.
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get files by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending files.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get processing files.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to get completed files.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed files.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB']; // Added TB for future-proofing

        // FIX: strlen() doesn't work on integers, use log10 instead
        $factor = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $factor = min($factor, count($units) - 1); // Don't exceed array bounds

        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }
}
