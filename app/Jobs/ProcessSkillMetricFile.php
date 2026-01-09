<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Skill;
use App\Models\SkillMetricFile;
use App\Services\AIService;
use App\Services\FileParserService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSkillMetricFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes
    public $backoff = [60, 120, 300]; // Retry after 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SkillMetricFile $skillMetricFile
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        FileParserService $fileParser,
        AIService $aiService
    ): void {
        try {
            Log::info('Processing skill metric file', [
                'file_id' => $this->skillMetricFile->id,
                'filename' => $this->skillMetricFile->original_filename,
            ]);

            // Update status to processing
            $this->skillMetricFile->update(['status' => 'processing']);

            // Step 1: Parse file and extract text
            $content = $fileParser->parse(
                $this->skillMetricFile->file_path,
                $this->skillMetricFile->file_type
            );

            Log::info('File parsed successfully', [
                'file_id' => $this->skillMetricFile->id,
                'content_length' => strlen($content),
            ]);

            // Step 2: Extract structured data (basic heuristics)
            $structuredData = $fileParser->extractStructuredData($content);

            // Step 3: Use AI to extract skills (or fallback to basic extraction)
            $skillData = $aiService->extractSkills($content);

            Log::info('Skills extracted', [
                'file_id' => $this->skillMetricFile->id,
                'source_skills' => $skillData['source_skills'],
                'target_skills' => $skillData['target_skills'],
            ]);

            // Step 4: Match skills to database
            $sourceSkillIds = $this->matchSkills($skillData['source_skills']);
            $targetSkillIds = $this->matchSkills($skillData['target_skills']);

            // Step 5: Update file with extracted data
            $this->skillMetricFile->update([
                'extracted_data' => array_merge($skillData, $structuredData),
                'source_skills' => $sourceSkillIds,
                'target_skills' => $targetSkillIds,
            ]);

            // Step 6: Generate course outline using AI
            if (!empty($sourceSkillIds) && !empty($targetSkillIds)) {
                $this->generateCourse($aiService, $skillData, $sourceSkillIds, $targetSkillIds);
            }

            // Step 7: Mark as completed
            $this->skillMetricFile->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            Log::info('Skill metric file processed successfully', [
                'file_id' => $this->skillMetricFile->id,
            ]);

            // Send notification
            if ($this->skillMetricFile->uploader) {
                NotificationService::custom(
                    $this->skillMetricFile->uploader,
                    'Skill Metric File Processed',
                    "Your file \"{$this->skillMetricFile->original_filename}\" has been processed successfully",
                    "/skill-metric-files/{$this->skillMetricFile->id}",
                    [
                        'file_id' => $this->skillMetricFile->id,
                        'courses_generated' => $this->skillMetricFile->courses()->count(),
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::error('Failed to process skill metric file', [
                'file_id' => $this->skillMetricFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update status to failed
            $this->skillMetricFile->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            // Send error notification
            if ($this->skillMetricFile->uploader) {
                NotificationService::custom(
                    $this->skillMetricFile->uploader,
                    'File Processing Failed',
                    "Failed to process \"{$this->skillMetricFile->original_filename}\": {$e->getMessage()}",
                    "/skill-metric-files/{$this->skillMetricFile->id}"
                );
            }

            // Re-throw for retry mechanism
            throw $e;
        }
    }

    /**
     * Match skill names to database skill IDs.
     */
    protected function matchSkills(array $skillNames): array
    {
        $skillIds = [];

        foreach ($skillNames as $skillName) {
            // Try to find existing skill
            $skill = Skill::where('name', 'ILIKE', $skillName)->first();

            // If not found, try fuzzy search
            if (!$skill) {
                $skill = Skill::where('name', 'ILIKE', "%{$skillName}%")->first();
            }

            if ($skill) {
                $skillIds[] = $skill->id;
            } else {
                // Create new skill if not found
                $skill = Skill::create([
                    'name' => $skillName,
                    'category' => 'other',
                    'description' => "Auto-detected from skill metric file",
                ]);
                $skillIds[] = $skill->id;
            }
        }

        return array_unique($skillIds);
    }

    /**
     * Generate course from extracted skills.
     */
    protected function generateCourse(
        AIService $aiService,
        array $skillData,
        array $sourceSkillIds,
        array $targetSkillIds
    ): void {
        // For each source-target skill pair, generate a course
        foreach ($targetSkillIds as $targetSkillId) {
            $sourceSkillId = $sourceSkillIds[0] ?? null;

            if (!$sourceSkillId) {
                continue;
            }

            // Check if course already exists for this skill pair
            $existingCourse = Course::where('source_skill_id', $sourceSkillId)
                ->where('target_skill_id', $targetSkillId)
                ->where('skill_metric_file_id', $this->skillMetricFile->id)
                ->first();

            if ($existingCourse) {
                continue; // Skip if already exists
            }

            $sourceSkill = Skill::find($sourceSkillId);
            $targetSkill = Skill::find($targetSkillId);

            if (!$sourceSkill || !$targetSkill) {
                Log::warning('Skill not found', [
                    'source_skill_id' => $sourceSkillId,
                    'target_skill_id' => $targetSkillId,
                ]);
                continue;
            }

            // Generate course outline using AI with skill NAMES
            $courseData = $aiService->generateCourseOutline([
                'source_skills' => [$sourceSkill->name],
                'target_skills' => [$targetSkill->name],
                'experience_level' => $skillData['experience_level'] ?? 'intermediate',
                'timeline_weeks' => $this->skillMetricFile->timeline_weeks,
                'special_requirements' => $this->skillMetricFile->special_requirements,
            ]);

            // Create course
            $course = Course::create([
                'skill_metric_file_id' => $this->skillMetricFile->id,
                'source_skill_id' => $sourceSkillId,
                'target_skill_id' => $targetSkillId,
                'title' => $courseData['title'],
                'description' => $courseData['description'],
                'difficulty_level' => $courseData['difficulty_level'] ?? 'intermediate',
                'estimated_hours' => $courseData['estimated_hours'] ?? 80,
                'status' => 'ai_generated',
                'ai_generated_outline' => $courseData,
            ]);

            // Create modules from outline
            if (isset($courseData['modules']) && is_array($courseData['modules'])) {
                foreach ($courseData['modules'] as $moduleData) {
                    CourseModule::create([
                        'course_id' => $course->id,
                        'title' => $moduleData['title'],
                        'description' => $moduleData['description'] ?? null,
                        'order_index' => $moduleData['order_index'],
                        'content_type' => 'text',
                        'comparison_points' => $moduleData['comparison_points'] ?? [],
                        'estimated_minutes' => $moduleData['estimated_minutes'] ?? 120,
                        'is_published' => false, // Requires curator review
                    ]);
                }
            }

            Log::info('Course generated from skill metric file', [
                'file_id' => $this->skillMetricFile->id,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'source_skill' => $sourceSkill->name,
                'target_skill' => $targetSkill->name,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Skill metric file processing failed permanently', [
            'file_id' => $this->skillMetricFile->id,
            'error' => $exception->getMessage(),
        ]);

        $this->skillMetricFile->update([
            'status' => 'failed',
            'error_message' => 'Processing failed after multiple retries: ' . $exception->getMessage(),
        ]);
    }
}
