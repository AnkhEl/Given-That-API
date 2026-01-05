<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            // Link to skill metric file (if generated from file)
            $table->foreignId('skill_metric_file_id')
                ->nullable()
                ->constrained('skill_metric_files')
                ->nullOnDelete();

            // Source and target skills (the transition)
            $table->foreignId('source_skill_id')
                ->nullable()
                ->constrained('skills')
                ->nullOnDelete();
            $table->foreignId('target_skill_id')
                ->nullable()
                ->constrained('skills')
                ->nullOnDelete();

            // Course information
            $table->string('title', 500);
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->text('prerequisites')->nullable();

            // Course metadata
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])
                ->default('intermediate');
            $table->integer('estimated_hours')->nullable();

            // Course status
            $table->enum('status', [
                'draft',
                'ai_generated',
                'in_curation',
                'published',
                'archived'
            ])->default('draft');

            // AI-generated outline (stored as JSON)
            $table->json('ai_generated_outline')->nullable();

            // Curation
            $table->foreignId('curated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            // Visual
            $table->string('thumbnail_url', 500)->nullable();

            // Tags and metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('source_skill_id');
            $table->index('target_skill_id');
            $table->index('status');
            $table->index('slug');
            $table->index('published_at');

            // Composite index for skill transitions
            $table->index(['source_skill_id', 'target_skill_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
