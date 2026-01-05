<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'company_id',
        'status',
        'enrolled_at',
        'started_at',
        'completed_at',
        'completion_percentage',
        'last_accessed_at',
        'certificate_issued_at',
        'certificate_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enrolled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'certificate_issued_at' => 'datetime',
        'completion_percentage' => 'decimal:2',
    ];

    /**
     * Get the user enrolled in the course.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all progress records for this enrollment.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Get assessment attempts for this enrollment.
     */
    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * Get the certificate for this enrollment.
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * Check if enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if enrollment is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if enrollment is dropped.
     */
    public function isDropped(): bool
    {
        return $this->status === 'dropped';
    }

    /**
     * Mark enrollment as started.
     */
    public function markAsStarted(): void
    {
        if ($this->status === 'enrolled') {
            $this->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }
    }

    /**
     * Mark enrollment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_percentage' => 100.00,
        ]);
    }

    /**
     * Update last accessed timestamp.
     */
    public function updateLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Calculate and update completion percentage.
     */
    public function recalculateProgress(): void
    {
        $totalModules = $this->course->modules()->count();

        if ($totalModules === 0) {
            return;
        }

        $completedModules = $this->progress()
            ->where('status', 'completed')
            ->count();

        $percentage = ($completedModules / $totalModules) * 100;

        $this->update(['completion_percentage' => round($percentage, 2)]);

        // Auto-complete enrollment if 100%
        if ($percentage >= 100 && $this->status !== 'completed') {
            $this->markAsCompleted();
        }
    }

    /**
     * Get completed modules count.
     */
    public function getCompletedModulesCountAttribute(): int
    {
        return $this->progress()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Get total modules count.
     */
    public function getTotalModulesCountAttribute(): int
    {
        return $this->course->modules()->count();
    }

    /**
     * Get total time spent in seconds.
     */
    public function getTotalTimeSpentAttribute(): int
    {
        return $this->progress()->sum('time_spent_seconds');
    }

    /**
     * Scope to get active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'in_progress']);
    }

    /**
     * Scope to get completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get enrollments by company.
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get enrollments by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
