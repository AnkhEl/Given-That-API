<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'module_id',
        'title',
        'description',
        'assessment_type',
        'questions',
        'passing_score',
        'time_limit_minutes',
        'max_attempts',
        'is_required',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'questions' => 'array',
        'passing_score' => 'decimal:2',
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Get the course that owns this assessment.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the module that owns this assessment (if module-specific).
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    /**
     * Get all attempts for this assessment.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * Get attempts by a specific user.
     */
    public function attemptsByUser(int $userId): HasMany
    {
        return $this->attempts()->where('user_id', $userId);
    }

    /**
     * Calculate total points available.
     */
    public function getTotalPointsAttribute(): int
    {
        $questions = $this->questions ?? [];
        return collect($questions)->sum('points');
    }

    /**
     * Get question count.
     */
    public function getQuestionCountAttribute(): int
    {
        return count($this->questions ?? []);
    }

    /**
     * Check if assessment is timed.
     */
    public function isTimed(): bool
    {
        return $this->time_limit_minutes !== null && $this->time_limit_minutes > 0;
    }

    /**
     * Check if assessment is required.
     */
    public function isRequired(): bool
    {
        return $this->is_required;
    }

    /**
     * Get remaining attempts for a user.
     */
    public function getRemainingAttempts(int $userId): int
    {
        $attemptCount = $this->attemptsByUser($userId)->count();
        return max(0, $this->max_attempts - $attemptCount);
    }

    /**
     * Check if user has attempts remaining.
     */
    public function hasAttemptsRemaining(int $userId): bool
    {
        return $this->getRemainingAttempts($userId) > 0;
    }

    /**
     * Get best attempt score for a user.
     */
    public function getBestScore(int $userId): ?float
    {
        return $this->attemptsByUser($userId)
            ->max('score');
    }

    /**
     * Check if user has passed this assessment.
     */
    public function hasUserPassed(int $userId): bool
    {
        return $this->attemptsByUser($userId)
            ->where('passed', true)
            ->exists();
    }

    /**
     * Scope to get assessments by course.
     */
    public function scopeByCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to get required assessments.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}
