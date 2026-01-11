<?php

namespace App\Jobs;

use App\Models\Skill;
use App\Models\SkillMetricFile;
use App\Services\AIService;
use App\Services\FileParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractSkillsFromFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120; // 2 minutes max
    public array $backoff = [30, 60, 120];

    public function __construct(
        public SkillMetricFile $skillMetricFile
    ) {}

    public function handle(
        FileParserService $fileParser,
        AIService $aiService
    ): void {
        try {
            Log::info('Stage 1: Extracting skills from file', [
                'file_id' => $this->skillMetricFile->id,
                'filename' => $this->skillMetricFile->original_filename,
            ]);

            // Update status
            $this->skillMetricFile->update(['status' => 'processing']);

            // Step 1: Parse file
            $content = $fileParser->parse(
                $this->skillMetricFile->file_path,
                $this->skillMetricFile->file_type
            );

            Log::info('File parsed successfully', [
                'file_id' => $this->skillMetricFile->id,
                'content_length' => strlen($content),
            ]);

            // Step 2: Basic structured extraction
            $structuredData = $fileParser->extractStructuredData($content);

            // Step 3: AI skill extraction (fast - ~30-60 seconds)
            $skillData = $aiService->extractSkills($content);

            Log::info('Skills extracted via AI', [
                'file_id' => $this->skillMetricFile->id,
                'source_skills' => $skillData['source_skills'],
                'target_skills' => $skillData['target_skills'],
                'confidence' => $skillData['confidence'],
            ]);

            // Step 4: Match skills to database IDs
            $sourceSkillIds = $this->matchSkills($skillData['source_skills']);
            $targetSkillIds = $this->matchSkills($skillData['target_skills']);

            Log::info('Skills matched to database', [
                'file_id' => $this->skillMetricFile->id,
                'source_ids' => $sourceSkillIds,
                'target_ids' => $targetSkillIds,
            ]);

            // Step 5: Update file with extracted data
            $this->skillMetricFile->update([
                'extracted_data' => array_merge($skillData, $structuredData),
                'source_skills' => $sourceSkillIds,
                'target_skills' => $targetSkillIds,
                'status' => 'skills_extracted', // New status!
            ]);

            // Step 6: Dispatch next stage
            if (!empty($sourceSkillIds) && !empty($targetSkillIds)) {
                GenerateCoursesFromStrategy::dispatch($this->skillMetricFile);

                Log::info('Stage 1 complete - dispatching Stage 2', [
                    'file_id' => $this->skillMetricFile->id,
                ]);
            } else {
                throw new \Exception('No skills extracted - cannot proceed to course generation');
            }

        } catch (\Exception $e) {
            Log::error('Stage 1 failed: Skill extraction', [
                'file_id' => $this->skillMetricFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->skillMetricFile->update([
                'status' => 'failed',
                'error_message' => 'Skill extraction failed: ' . $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Match skill names to database IDs
     */
    protected function matchSkills(array $skillNames): array
    {
        $skillIds = [];

        foreach ($skillNames as $skillName) {
            // Try exact match
            $skill = Skill::where('name', 'ILIKE', $skillName)->first();

            // Try fuzzy match
            if (!$skill) {
                $skill = Skill::where('name', 'ILIKE', "%{$skillName}%")->first();
            }

            if ($skill) {
                $skillIds[] = $skill->id;
                Log::debug("Matched skill: {$skillName} → {$skill->name} (ID: {$skill->id})");
            } else {
                // Create new skill
                $skill = Skill::create([
                    'name' => $skillName,
                    'category' => 'other',
                    'description' => "Auto-detected from skill metric file",
                ]);
                $skillIds[] = $skill->id;
                Log::info("Created new skill: {$skillName} (ID: {$skill->id})");
            }
        }

        return array_unique($skillIds);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Stage 1 failed permanently', [
            'file_id' => $this->skillMetricFile->id,
            'error' => $exception->getMessage(),
        ]);

        $this->skillMetricFile->update([
            'status' => 'failed',
            'error_message' => 'Skill extraction failed: ' . $exception->getMessage(),
        ]);
    }
}
