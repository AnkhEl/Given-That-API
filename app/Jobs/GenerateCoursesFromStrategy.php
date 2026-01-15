<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\SkillMetricFile;
use App\Services\AIService;
use App\Services\CourseGenerationStrategy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCoursesFromStrategy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 900; // 15 minutes (allows multiple AI calls)
    public $backoff = [120, 300];

    public function __construct(
        public SkillMetricFile $skillMetricFile
    ) {}

    public function handle(
        CourseGenerationStrategy $strategy,
        AIService $aiService
    ): void {
        try {
            Log::info('Stage 2: Generating courses from strategy', [
                'file_id' => $this->skillMetricFile->id,
            ]);

            // Check if courses already exist (idempotency)
            $existingCoursesCount = $this->skillMetricFile->courses()->count();

            if ($existingCoursesCount > 0) {
                Log::info('Courses already exist for this file, skipping generation', [
                    'file_id' => $this->skillMetricFile->id,
                    'existing_courses_count' => $existingCoursesCount,
                ]);

                // Mark as completed if not already
                if ($this->skillMetricFile->status !== 'courses_generated') {
                    $this->skillMetricFile->update(['status' => 'courses_generated']);
                }

                // Dispatch module creation jobs if not done yet
                $this->ensureModuleJobsDispatched();

                return; // Job completes successfully
            }

            $this->skillMetricFile->update(['status' => 'generating_courses']);

            // Step 1: Determine strategy
            $strategyResult = $strategy->determine(
                $this->skillMetricFile->source_skills,
                $this->skillMetricFile->target_skills
            );

            Log::info('Course generation strategy determined', [
                'file_id' => $this->skillMetricFile->id,
                'strategy_type' => $strategyResult['type'],
                'strategy_reason' => $strategyResult['reason'],
                'course_count' => count($strategyResult['courses'] ?? []),
            ]);

            // Store strategy for audit trail
            $this->skillMetricFile->update([
                'extracted_data' => array_merge(
                    $this->skillMetricFile->extracted_data ?? [],
                    ['generation_strategy' => $strategyResult]
                ),
            ]);

            // Step 2: Handle based on strategy type
            if ($strategyResult['type'] === 'needs_review') {
                Log::warning('Course generation needs manual review - using fallback strategy', [
                    'file_id' => $this->skillMetricFile->id,
                    'reason' => $strategyResult['reason'],
                ]);

                // 🔥 FIX #2: Use fallback strategy instead of stopping
                if (isset($strategyResult['fallback'])) {
                    $strategyResult = $strategyResult['fallback'];

                    Log::info('Using fallback strategy', [
                        'file_id' => $this->skillMetricFile->id,
                        'fallback_type' => $strategyResult['type'],
                        'course_count' => count($strategyResult['courses']),
                    ]);
                } else {
                    // No fallback - mark for review and complete job
                    $this->skillMetricFile->update([
                        'status' => 'needs_review',
                        'error_message' => $strategyResult['reason'],
                        'processed_at' => now(),
                    ]);
                    return; // Job completes (not a failure)
                }
            }

            // Step 3: Generate courses based on strategy
            $coursesGenerated = 0;
            $generatedCourses = []; // 🔥 Collect courses first

            foreach ($strategyResult['courses'] as $courseSpec) {
                try {
                    $course = $this->generateCourse($aiService, $courseSpec);

                    if ($course) {
                        $coursesGenerated++;
                        $generatedCourses[] = $course; // 🔥 Store for later
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to generate individual course', [
                        'file_id' => $this->skillMetricFile->id,
                        'course_spec' => $courseSpec,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other courses
                }
            }

            // Dispatch module jobs AFTER all courses are generated
            foreach ($generatedCourses as $course) {
                CreateCourseModules::dispatch($course);

                Log::info('Dispatched module creation job', [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                ]);
            }

            if ($coursesGenerated === 0) {
                throw new \Exception('No courses were generated successfully');
            }

            // Step 4: Update file status
            $this->skillMetricFile->update([
                'status' => 'courses_generated',
            ]);

            Log::info('Stage 2 complete', [
                'file_id' => $this->skillMetricFile->id,
                'courses_generated' => $coursesGenerated,
            ]);

        } catch (\Exception $e) {
            Log::error('Stage 2 failed: Course generation', [
                'file_id' => $this->skillMetricFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->skillMetricFile->update([
                'status' => 'failed',
                'error_message' => 'Course generation failed: ' . $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * 🔥 FIX #3: Ensure module creation jobs are dispatched for all courses
     */
    protected function ensureModuleJobsDispatched(): void
    {
        $coursesWithoutModules = $this->skillMetricFile->courses()
            ->doesntHave('modules')
            ->get();

        if ($coursesWithoutModules->isEmpty()) {
            Log::info('All courses already have modules', [
                'file_id' => $this->skillMetricFile->id,
            ]);
            return;
        }

        Log::info('Dispatching module creation for courses without modules', [
            'file_id' => $this->skillMetricFile->id,
            'courses_count' => $coursesWithoutModules->count(),
        ]);

        foreach ($coursesWithoutModules as $course) {
            CreateCourseModules::dispatch($course);
        }
    }

    /**
     * Generate a single course from a course specification
     */
    protected function generateCourse(AIService $aiService, array $courseSpec): ?Course
    {
        Log::info('Generating course outline', [
            'source_skills' => $courseSpec['source_skills'],
            'target_skills' => $courseSpec['target_skills'],
        ]);

        // Check if course already exists
        $existingCourse = Course::where('skill_metric_file_id', $this->skillMetricFile->id)
            ->whereJsonContains('ai_generated_outline->source_skills', $courseSpec['source_skills'])
            ->whereJsonContains('ai_generated_outline->target_skills', $courseSpec['target_skills'])
            ->first();

        if ($existingCourse) {
            Log::info('Course already exists, skipping', [
                'course_id' => $existingCourse->id,
            ]);
            return $existingCourse;
        }

        // Generate course outline using AI
        $courseOutline = $aiService->generateCourseOutline([
            'source_skills' => $courseSpec['source_skills'],
            'target_skills' => $courseSpec['target_skills'],
            'experience_level' => $this->skillMetricFile->extracted_data['experience_level'] ?? 'intermediate',
            'timeline_weeks' => $this->skillMetricFile->timeline_weeks,
            'special_requirements' => $this->skillMetricFile->special_requirements,
        ]);

        // Create course record
        $course = Course::create([
            'skill_metric_file_id' => $this->skillMetricFile->id,
            'source_skill_id' => $courseSpec['source_skill_ids'][0] ?? null,
            'target_skill_id' => $courseSpec['target_skill_ids'][0] ?? null,
            'title' => $courseOutline['title'],
            'description' => $courseOutline['description'],
            'difficulty_level' => $courseOutline['difficulty_level'] ?? 'intermediate',
            'estimated_hours' => $courseOutline['estimated_hours'] ?? 80,
            'status' => 'ai_generated',
            'ai_generated_outline' => array_merge($courseOutline, [
                'source_skills' => $courseSpec['source_skills'],
                'target_skills' => $courseSpec['target_skills'],
                'strategy_type' => $courseSpec['domain'] ?? 'transformation',
            ]),
        ]);

        Log::info('Course created', [
            'file_id' => $this->skillMetricFile->id,
            'course_id' => $course->id,
            'course_title' => $course->title,
        ]);

        return $course;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Stage 2 failed permanently', [
            'file_id' => $this->skillMetricFile->id,
            'error' => $exception->getMessage(),
        ]);

        $this->skillMetricFile->update([
            'status' => 'failed',
            'error_message' => 'Course generation failed: ' . $exception->getMessage(),
        ]);
    }
}
