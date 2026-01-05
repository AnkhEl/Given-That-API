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
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('enrollment_id')
                ->constrained('enrollments')
                ->onDelete('cascade');
            $table->foreignId('module_id')
                ->constrained('course_modules')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Progress status
            $table->enum('status', [
                'not_started',
                'in_progress',
                'completed'
            ])->default('not_started');

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Time tracking
            $table->integer('time_spent_seconds')->default(0);

            // Progress percentage (for modules with sub-sections)
            $table->decimal('progress_percentage', 5, 2)->default(0.00);

            // Resume position (e.g., video timestamp, scroll position)
            $table->text('last_position')->nullable();

            // User notes for this module
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('enrollment_id');
            $table->index('module_id');
            $table->index('user_id');
            $table->index(['enrollment_id', 'module_id']);

            // Unique constraint - one progress record per enrollment per module
            $table->unique(['enrollment_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
