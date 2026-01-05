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
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('assessment_id')
                ->constrained('assessments')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Enrollment relationship - will be added in Group 4
            $table->unsignedBigInteger('enrollment_id')->nullable();

            // Attempt information
            $table->integer('attempt_number');

            // Answers (stored as JSON)
            // Example: {
            //   "q1": "Eloquent",
            //   "q2": "MVC",
            //   "q3": ["Routing", "Middleware", "Controllers"]
            // }
            $table->json('answers');

            // Scoring
            $table->decimal('score', 5, 2)->nullable(); // e.g., 85.50
            $table->boolean('passed')->nullable();

            // Timing
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->integer('time_taken_seconds')->nullable();

            // Feedback (JSON with per-question feedback)
            // Example: {
            //   "q1": {"correct": true, "points_earned": 10},
            //   "q2": {"correct": false, "points_earned": 0, "explanation": "..."}
            // }
            $table->json('feedback')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('assessment_id');
            $table->index('user_id');
            $table->index('enrollment_id'); // Will be foreign key in Group 4
            $table->index(['user_id', 'assessment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
