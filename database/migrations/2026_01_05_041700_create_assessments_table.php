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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            // Can be attached to course or specific module
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onDelete('cascade');
            $table->foreignId('module_id')
                ->nullable()
                ->constrained('course_modules')
                ->onDelete('cascade');

            // Assessment information
            $table->string('title', 500);
            $table->text('description')->nullable();

            $table->enum('assessment_type', [
                'quiz',
                'project',
                'coding_challenge',
                'essay'
            ])->default('quiz');

            // Questions (stored as JSON)
            // Example: [
            //   {
            //     "id": "q1",
            //     "type": "multiple_choice",
            //     "question": "What is Laravel's ORM called?",
            //     "options": ["Doctrine", "Eloquent", "Propel", "ActiveRecord"],
            //     "correct_answer": "Eloquent",
            //     "points": 10,
            //     "explanation": "Laravel uses Eloquent ORM..."
            //   }
            // ]
            $table->json('questions');

            // Scoring
            $table->decimal('passing_score', 5, 2); // e.g., 70.00 for 70%

            // Time and attempts
            $table->integer('time_limit_minutes')->nullable();
            $table->integer('max_attempts')->default(3);

            // Is this assessment required to complete the module/course?
            $table->boolean('is_required')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('course_id');
            $table->index('module_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
