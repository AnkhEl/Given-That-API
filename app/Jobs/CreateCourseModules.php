<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseModule;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateCourseModules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // Increased from 120 to 5 minutes
    public $backoff = [30, 60];

    public function __construct(
        public Course $course
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Stage 3: Creating modules for course', [
                'course_id' => $this->course->id,
                'course_title' => $this->course->title,
            ]);

            // Check if modules already exist (idempotency check)
            $existingModulesCount = $this->course->modules()->count();

            if ($existingModulesCount > 0) {
                Log::info('Modules already exist for this course, skipping creation', [
                    'course_id' => $this->course->id,
                    'existing_modules_count' => $existingModulesCount,
                ]);

                // Still proceed to completion check
                $this->checkAndMarkCompletion();
                return;
            }

            $outline = $this->course->ai_generated_outline;

            if (!isset($outline['modules']) || !is_array($outline['modules'])) {
                throw new \Exception('No modules found in course outline');
            }

            $modulesCreated = 0;

            // Create modules from AI outline
            foreach ($outline['modules'] as $moduleData) {
                // Generate unique slug with fallback
                $baseSlug = isset($moduleData['title'])
                    ? Str::slug($moduleData['title'])
                    : 'module-' . ($moduleData['order_index'] ?? $modulesCreated + 1);

                $slug = $this->generateUniqueSlug($this->course->id, $baseSlug);

                CourseModule::create([
                    'course_id' => $this->course->id,
                    'title' => $moduleData['title'],
                    'slug' => $slug, // Use unique slug
                    'description' => $moduleData['description'] ?? null,
                    'order_index' => $moduleData['order_index'],
                    'content_type' => 'text',
                    'comparison_points' => $moduleData['comparison_points'] ?? [],
                    'estimated_minutes' => $moduleData['estimated_minutes'] ?? 120,
                    'is_published' => false, // Requires curator review
                ]);

                $modulesCreated++;
            }

            Log::info('Modules created successfully', [
                'course_id' => $this->course->id,
                'modules_created' => $modulesCreated,
            ]);

            // Check completion
            $this->checkAndMarkCompletion();

        } catch (\Exception $e) {
            Log::error('Stage 3 failed: Module creation', [
                'course_id' => $this->course->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     *  Extract completion check to reusable method
     */
    protected function checkAndMarkCompletion(): void
    {
        // Check if this is the last course for this file
        $file = $this->course->skillMetricFile;

        if (!$file) {
            Log::warning('Course has no associated skill metric file', [
                'course_id' => $this->course->id,
            ]);
            return;
        }

        $totalCourses = $file->courses()->count();
        $completedCourses = $file->courses()
            ->whereHas('modules')
            ->count();

        Log::info('Course completion check', [
            'file_id' => $file->id,
            'total_courses' => $totalCourses,
            'completed_courses' => $completedCourses,
        ]);

        // If all courses have modules, mark file as complete
        if ($totalCourses === $completedCourses) {
            $file->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            Log::info('All courses complete - file processing finished', [
                'file_id' => $file->id,
            ]);

            // Send notification
            if ($file->uploader) {
                NotificationService::custom(
                    $file->uploader,
                    'Skill Metric File Processed',
                    "Your file \"{$file->original_filename}\" has been processed successfully. {$totalCourses} course(s) generated.",
                    "/skill-metric-files/{$file->id}",
                    [
                        'file_id' => $file->id,
                        'courses_generated' => $totalCourses,
                    ]
                );
            }
        }
    }

    /**
     *  Generate unique slug for module
     */
    protected function generateUniqueSlug(int $courseId, string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        // Check if slug exists for this course
        while (CourseModule::where('course_id', $courseId)
            ->where('slug', $slug)
            ->exists()) {

            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Stage 3 failed permanently', [
            'course_id' => $this->course->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark course as having issues
        $this->course->update([
            'status' => 'generation_failed',
        ]);

        // If parent file is still in "generating" state, mark as failed
        $file = $this->course->skillMetricFile;
        if ($file && in_array($file->status, ['generating_courses', 'courses_generated'])) {
            $file->update([
                'status' => 'failed',
                'error_message' => 'Module creation failed for one or more courses',
                'processed_at' => now(),
            ]);
        }
    }
}
