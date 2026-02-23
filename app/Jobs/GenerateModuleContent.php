<?php

namespace App\Jobs;

use App\Models\CourseModule;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateModuleContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minutes per module
    public $backoff = [120, 300]; // Retry after 2 mins, then 5 mins

    public function __construct(
        public CourseModule $module
    ) {}

    public function handle(AIService $aiService): void
    {
        try {
            Log::info('Generating detailed content for module', [
                'module_id' => $this->module->id,
                'module_title' => $this->module->title,
                'course_id' => $this->module->course_id,
            ]);

            // 🔥 Idempotency: Check if content already generated
            if (!empty($this->module->content)) {
                Log::info('Module content already exists, skipping generation', [
                    'module_id' => $this->module->id,
                ]);
                return;
            }

            // Load necessary relationships
            $this->module->load('course.sourceSkill', 'course.targetSkill');

            // Prepare context for AI
            $context = [
                'module_title' => $this->module->title,
                'module_description' => $this->module->description,
                'comparison_points' => $this->module->comparison_points ?? [],
                'source_skill' => $this->module->course->sourceSkill?->name ?? 'Source Technology',
                'target_skill' => $this->module->course->targetSkill?->name ?? 'Target Technology',
                'order_index' => $this->module->order_index,
                'course_title' => $this->module->course->title,
                'estimated_minutes' => $this->module->estimated_minutes,
            ];

            // Generate rich content using AI
            $content = $aiService->generateModuleContent($context);

            // Update module with generated content
            $this->module->update([
                'content' => $content, // Your model already casts this to JSON
            ]);

            Log::info('Module content generated successfully', [
                'module_id' => $this->module->id,
                'content_sections' => is_array($content) ? array_keys($content) : [],
                'code_examples_count' => count($content['code_examples'] ?? []),
                'tutorial_steps' => count($content['tutorial'] ?? []),
            ]);

        } catch (\Exception $e) {
            Log::error('Module content generation failed', [
                'module_id' => $this->module->id,
                'module_title' => $this->module->title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Module content generation failed permanently', [
            'module_id' => $this->module->id,
            'module_title' => $this->module->title,
            'error' => $exception->getMessage(),
        ]);

        // Mark module with error so curator knows it needs manual work
        $this->module->update([
            'content' => [
                'error' => 'AI generation failed',
                'message' => 'This module requires manual content creation by a curator.',
                'failed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
