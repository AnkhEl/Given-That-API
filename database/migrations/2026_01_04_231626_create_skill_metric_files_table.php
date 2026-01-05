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
        Schema::create('skill_metric_files', function (Blueprint $table) {
            $table->id();

            // Company and uploader
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // File information
            $table->string('original_filename');
            $table->string('file_path', 500);
            $table->integer('file_size')->nullable(); // in bytes
            $table->enum('file_type', ['pdf', 'docx', 'xlsx'])->nullable();

            // Processing status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed'
            ])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();

            // Extracted data from file
            $table->json('extracted_data')->nullable(); // Raw extracted text/data
            $table->json('source_skills')->nullable(); // Array of identified source skills
            $table->json('target_skills')->nullable(); // Array of identified target skills

            // Training details
            $table->integer('trainee_count')->default(1);
            $table->integer('timeline_weeks')->nullable();
            $table->text('special_requirements')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('company_id');
            $table->index('uploaded_by');
            $table->index('status');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_metric_files');
    }
};
