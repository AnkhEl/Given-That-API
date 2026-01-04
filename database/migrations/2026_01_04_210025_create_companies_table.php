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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();

            // Subscription information
            $table->enum('subscription_tier', ['starter', 'professional', 'enterprise'])
                ->default('starter');
            $table->enum('subscription_status', ['active', 'trial', 'suspended', 'cancelled'])
                ->default('trial');
            $table->integer('max_learners')->default(10);
            $table->integer('max_courses_per_month')->default(5);
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Branding
            $table->string('logo_url', 500)->nullable();

            // Additional settings stored as JSON
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
