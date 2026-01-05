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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('enrollment_id')
                ->constrained('enrollments')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('course_id')
                ->constrained('courses')
                ->onDelete('cascade');

            // Certificate information
            $table->string('certificate_number', 100)->unique();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            // Certificate file
            $table->string('certificate_url', 500);
            $table->string('verification_url', 500)->nullable();

            // Additional data (JSON)
            // Can store: completion_time, final_score, skills_earned, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('course_id');
            $table->index('certificate_number');
            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
