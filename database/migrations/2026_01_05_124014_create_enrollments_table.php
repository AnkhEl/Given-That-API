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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onDelete('cascade');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade');

            // Enrollment status
            $table->enum('status', [
                'enrolled',
                'in_progress',
                'completed',
                'dropped'
            ])->default('enrolled');

            // Timestamps
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Progress tracking
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->timestamp('last_accessed_at')->nullable();

            // Certificate
            $table->timestamp('certificate_issued_at')->nullable();
            $table->string('certificate_url', 500)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('course_id');
            $table->index('company_id');
            $table->index('status');
            $table->index(['user_id', 'course_id']);

            // Unique constraint - user can only enroll once per course
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
