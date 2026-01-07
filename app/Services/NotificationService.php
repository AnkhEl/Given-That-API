<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Send a notification to a user.
     */
    public static function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * Send notification when user is enrolled in a course.
     */
    public static function courseEnrolled(User $user, $course): Notification
    {
        return self::send(
            $user->id,
            'course_enrolled',
            'Enrolled in New Course',
            "You have been enrolled in \"{$course->title}\"",
            "/courses/{$course->id}",
            [
                'course_id' => $course->id,
                'course_title' => $course->title,
            ]
        );
    }

    /**
     * Send notification when a module is completed.
     */
    public static function moduleCompleted(User $user, $module, $course): Notification
    {
        return self::send(
            $user->id,
            'module_completed',
            'Module Completed! 🎉',
            "You completed \"{$module->title}\" in {$course->title}",
            "/courses/{$course->id}/modules/{$module->id}",
            [
                'course_id' => $course->id,
                'module_id' => $module->id,
                'module_title' => $module->title,
            ]
        );
    }

    /**
     * Send notification when entire course is completed.
     */
    public static function courseCompleted(User $user, $course): Notification
    {
        return self::send(
            $user->id,
            'course_completed',
            'Course Completed! 🎓',
            "Congratulations! You completed \"{$course->title}\"",
            "/courses/{$course->id}",
            [
                'course_id' => $course->id,
                'course_title' => $course->title,
            ]
        );
    }

    /**
     * Send notification when assessment is passed.
     */
    public static function assessmentPassed(User $user, $assessment, $attempt): Notification
    {
        return self::send(
            $user->id,
            'assessment_passed',
            'Assessment Passed! ✅',
            "You passed \"{$assessment->title}\" with {$attempt->score_percentage}%",
            "/courses/{$assessment->course_id}/assessments/{$assessment->id}",
            [
                'assessment_id' => $assessment->id,
                'attempt_id' => $attempt->id,
                'score' => $attempt->score,
                'score_percentage' => $attempt->score_percentage,
            ]
        );
    }

    /**
     * Send notification when assessment is failed.
     */
    public static function assessmentFailed(User $user, $assessment, $attempt): Notification
    {
        $remainingAttempts = $assessment->getRemainingAttempts($user->id);

        return self::send(
            $user->id,
            'assessment_failed',
            'Assessment Not Passed',
            "You scored {$attempt->score_percentage}% on \"{$assessment->title}\". You have {$remainingAttempts} attempt(s) remaining.",
            "/courses/{$assessment->course_id}/assessments/{$assessment->id}",
            [
                'assessment_id' => $assessment->id,
                'attempt_id' => $attempt->id,
                'score' => $attempt->score,
                'score_percentage' => $attempt->score_percentage,
                'remaining_attempts' => $remainingAttempts,
            ]
        );
    }

    /**
     * Send notification when certificate is issued.
     */
    public static function certificateIssued(User $user, $certificate): Notification
    {
        return self::send(
            $user->id,
            'certificate_issued',
            'Certificate Issued! 🏆',
            "Your certificate for \"{$certificate->course->title}\" is ready",
            "/certificates/{$certificate->id}",
            [
                'certificate_id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'course_id' => $certificate->course_id,
            ]
        );
    }

    /**
     * Send notification when new course is assigned by admin.
     */
    public static function courseAssigned(User $user, $course, User $assignedBy): Notification
    {
        return self::send(
            $user->id,
            'course_assigned',
            'New Course Assigned',
            "{$assignedBy->name} assigned you to \"{$course->title}\"",
            "/courses/{$course->id}",
            [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'assigned_by' => $assignedBy->name,
            ]
        );
    }

    /**
     * Send notification when new course is published (for interested users).
     */
    public static function newCoursePublished($course): void
    {
        // This could be expanded to notify users interested in specific skills
        // For now, you can manually call this for specific users
    }

    /**
     * Send a custom notification.
     */
    public static function custom(
        User $user,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): Notification {
        return self::send(
            $user->id,
            'custom',
            $title,
            $message,
            $actionUrl,
            $data
        );
    }

    /**
     * Send bulk notifications to multiple users.
     */
    public static function sendBulk(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): int {
        $count = 0;

        foreach ($userIds as $userId) {
            self::send($userId, $type, $title, $message, $actionUrl, $data);
            $count++;
        }

        return $count;
    }
}
