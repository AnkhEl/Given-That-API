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
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();

            // Course relationship
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onDelete('cascade');

            // Parent module (for nested modules/sub-sections)
            $table->foreignId('parent_module_id')
                ->nullable()
                ->constrained('course_modules')
                ->onDelete('cascade');

            // Module information
            $table->string('title', 500);
            $table->string('slug', 500);
            $table->text('description')->nullable();
            $table->integer('order_index');

            // Content
            $table->enum('content_type', [
                'text',
                'video',
                'interactive',
                'quiz'
            ])->default('text');
            $table->longText('content')->nullable();

            // Comparison points (JSON array of comparisons between source and target)
            // Example: [{"source_concept": "Magento Block", "target_concept": "Laravel Component", "explanation": "..."}]
            $table->json('comparison_points')->nullable();

            // Code examples (JSON array)
            // Example: [{"language": "php", "source_code": "...", "target_code": "...", "explanation": "..."}]
            $table->json('code_examples')->nullable();

            // Resources (JSON array of links, references)
            $table->json('resources')->nullable();

            // Time estimate
            $table->integer('estimated_minutes')->nullable();

            // Publishing
            $table->boolean('is_published')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('course_id');
            $table->index('parent_module_id');
            $table->index(['course_id', 'order_index']);
            $table->unique(['course_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_modules');
    }
};
