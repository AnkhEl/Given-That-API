<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old constraint
        DB::statement("
            ALTER TABLE skill_metric_files
            DROP CONSTRAINT IF EXISTS skill_metric_files_status_check;
        ");

        // Add new constraint with all statuses
        DB::statement("
            ALTER TABLE skill_metric_files
            ADD CONSTRAINT skill_metric_files_status_check
            CHECK (status IN (
                'pending',
                'processing',
                'skills_extracted',
                'generating_courses',
                'courses_generated',
                'completed',
                'failed',
                'needs_review'
            ));
        ");
    }

    public function down(): void
    {
        // Drop new constraint
        DB::statement("
            ALTER TABLE skill_metric_files
            DROP CONSTRAINT IF EXISTS skill_metric_files_status_check;
        ");

        // Restore old constraint (optional)
        DB::statement("
            ALTER TABLE skill_metric_files
            ADD CONSTRAINT skill_metric_files_status_check
            CHECK (status IN (
                'pending',
                'processing',
                'completed',
                'failed'
            ));
        ");
    }
};
