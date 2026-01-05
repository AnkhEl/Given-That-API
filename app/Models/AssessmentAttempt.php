<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'user_id',
        'enrollment_id',
        'attempt_number',
        'answers',
        'score',
        'passed',
        'started_at',
        'submitted_at',
        'time_taken_seconds',
        'feedback',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'answers' => 'array',
        'feedback' => 'array',
        'score' => 'decimal:2',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'time_taken_seconds' => 'integer',
        'attempt_number' => 'integer',
    ];

    /**
     * Get the assessment for this attempt.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the user who made this attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the enrollment this attempt belongs to.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Check if attempt was passed.
     */
    public function isPassed(): bool
    {
        return $this->passed ?? false;
    }

    /**
     * Check if attempt is submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Check if attempt is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->started_at !== null && $this->submitted_at === null;
    }

    /**
     * Get time taken in human-readable format.
     */
    public function getTimeTakenHumanAttribute(): string
    {
        if (!$this->time_taken_seconds) {
            return 'N/A';
        }

        $hours = floor($this->time_taken_seconds / 3600);
        $minutes = floor(($this->time_taken_seconds % 3600) / 60);
        $seconds = $this->time_taken_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Get score percentage.
     */
    public function getScorePercentageAttribute(): float
    {
        return $this->score ?? 0.0;
    }

    /**
     * Calculate and set score from answers.
     */
    public function calculateScore(): void
    {
        $assessment = $this->assessment;
        $questions = $assessment->questions ?? [];
        $answers = $this->answers ?? [];

        $totalPoints = 0;
        $earnedPoints = 0;
        $feedback = [];

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $points = $question['points'] ?? 0;
            $totalPoints += $points;

            $userAnswer = $answers[$questionId] ?? null;
            $correctAnswer = $question['correct_answer'] ?? null;

            $isCorrect = $this->checkAnswer($userAnswer, $correctAnswer, $question['type'] ?? 'multiple_choice');

            if ($isCorrect) {
                $earnedPoints += $points;
            }

            $feedback[$questionId] = [
                'correct' => $isCorrect,
                'points_earned' => $isCorrect ? $points : 0,
                'correct_answer' => $correctAnswer,
                'explanation' => $question['explanation'] ?? null,
            ];
        }

        $scorePercentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $scorePercentage >= $assessment->passing_score;

        $this->update([
            'score' => round($scorePercentage, 2),
            'passed' => $passed,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Check if an answer is correct.
     */
    private function checkAnswer($userAnswer, $correctAnswer, string $questionType): bool
    {
        if ($userAnswer === null) {
            return false;
        }

        switch ($questionType) {
            case 'multiple_choice':
            case 'true_false':
                return $userAnswer === $correctAnswer;

            case 'multiple_select':
                if (!is_array($userAnswer) || !is_array($correctAnswer)) {
                    return false;
                }
                sort($userAnswer);
                sort($correctAnswer);
                return $userAnswer === $correctAnswer;

            case 'coding_challenge':
                // For coding challenges, this would involve running tests
                // For now, we'll just check if answer matches expected output
                return $userAnswer === $correctAnswer;

            default:
                return false;
        }
    }

    /**
     * Scope to get passed attempts.
     */
    public function scopePassed($query)
    {
        return $query->where('passed', true);
    }

    /**
     * Scope to get failed attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('passed', false);
    }

    /**
     * Scope to get attempts by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
