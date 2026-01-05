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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Notification type
            $table->string('type', 100);
            // Examples: 'course_assigned', 'module_completed', 'certificate_issued',
            //           'assessment_failed', 'reminder', 'course_updated'

            // Notification content
            $table->string('title', 500);
            $table->text('message');

            // Action URL (where to go when clicked)
            $table->string('action_url', 500)->nullable();

            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Additional data (JSON)
            // Can store: course_id, module_id, related entity info
            $table->json('data')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index(['user_id', 'is_read']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
