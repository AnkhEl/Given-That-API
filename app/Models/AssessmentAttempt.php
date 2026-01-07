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
     * Get human-readable time taken.
     *
     * @return string
     */
    public function getTimeTakenHumanAttribute(): string
    {
        if (!$this->time_taken_seconds) {
            return 'N/A';
        }

        $seconds = $this->time_taken_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Get the score as a percentage.
     * This accessor ensures consistency with the calculated pass/fail decision.
     */
    public function getScorePercentageAttribute(): float
    {
        $totalPoints = $this->assessment->total_points;
        if ($totalPoints == 0) return 0;

        return round(($this->score / $totalPoints) * 100, 2);
    }

    /**
     * Calculate the score for this assessment attempt.
     * Handles multiple question types with appropriate scoring logic.
     * Rounds scores before determining pass/fail to avoid floating-point precision issues.
     */
    public function calculateScore(): void
    {
        $assessment = $this->assessment;
        $questions = $assessment->questions;
        $userAnswers = $this->answers;

        $totalScore = 0;
        $feedback = [];

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $userAnswer = $userAnswers[$questionId] ?? null;
            $correctAnswer = $question['correct_answer'];
            $points = $question['points'];

            $pointsEarned = 0;
            $isCorrect = false;

            // Score based on question type
            if ($question['type'] === 'multiple_select') {
                // Partial credit for multiple select questions
                if (is_array($userAnswer) && is_array($correctAnswer)) {
                    $correctCount = count(array_intersect($userAnswer, $correctAnswer));
                    $wrongCount = count(array_diff($userAnswer, $correctAnswer));
                    $totalCorrect = count($correctAnswer);

                    // Calculate partial credit with penalty for wrong selections
                    // Formula: (correct_selections - wrong_selections) / total_correct * points
                    // Minimum score is 0 (can't go negative)
                    if ($correctCount > 0) {
                        $pointsEarned = max(0, (($correctCount - $wrongCount) / $totalCorrect) * $points);
                    }

                    // Only mark as fully correct if all correct answers selected and no wrong ones
                    $isCorrect = $correctCount === $totalCorrect && count($userAnswer) === $totalCorrect;
                }
            } else {
                // Binary scoring for other question types (multiple_choice, true_false, coding_challenge)
                if (is_array($correctAnswer)) {
                    // Handle cases where correct answer could be one of multiple options
                    $isCorrect = in_array($userAnswer, $correctAnswer);
                } else {
                    // Standard string comparison
                    $isCorrect = $userAnswer === $correctAnswer;
                }
                $pointsEarned = $isCorrect ? $points : 0;
            }

            $totalScore += $pointsEarned;

            // Build feedback for this question
            $feedback[$questionId] = [
                'correct' => $isCorrect,
                'points_earned' => round($pointsEarned, 2),
                'points_possible' => $points,
                'user_answer' => $userAnswer,
                'correct_answer' => $correctAnswer,
                'explanation' => $question['explanation'] ?? null,
            ];
        }

        // Round score BEFORE calculating percentage
        // This prevents floating-point precision issues where 69.9999% displays as 70% but fails
        $totalScore = round($totalScore, 2);

        // Calculate percentage based on rounded score
        $totalPoints = $assessment->total_points;
        $percentage = $totalPoints > 0 ? round(($totalScore / $totalPoints) * 100, 2) : 0;

        // Determine pass/fail using rounded percentage
        // Now if student has 69.995%, it rounds to 70.00% and passes (if passing score is 70%)
        // This ensures UI display matches system decision
        $passed = $percentage >= $assessment->passing_score;

        // Update the attempt record
        $this->update([
            'score' => $totalScore,        // Rounded to 2 decimal places
            'passed' => $passed,            // Based on rounded percentage
            'feedback' => $feedback,        // Detailed per-question feedback
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
