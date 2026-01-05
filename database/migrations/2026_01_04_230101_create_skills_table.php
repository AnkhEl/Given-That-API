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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('category', [
                'programming_language',
                'framework',
                'database',
                'devops',
                'cloud',
                'frontend',
                'backend',
                'mobile',
                'cms',
                'testing',
                'other'
            ])->default('other');
            $table->text('description')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->integer('popularity_score')->default(0);
            $table->json('metadata')->nullable(); // Store additional info like version, related skills, etc.
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('category');
            $table->index('popularity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
