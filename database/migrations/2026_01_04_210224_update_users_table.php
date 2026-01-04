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
        Schema::table('users', function (Blueprint $table) {
            // Company relationship
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();

            // Role (we'll also use Spatie Permission, but keep this for quick checks)
            $table->enum('role', ['super_admin', 'company_admin', 'curator', 'trainee'])
                ->default('trainee')
                ->after('password');

            // Avatar
            $table->string('avatar_url', 500)->nullable()->after('role');

            // Status
            $table->boolean('is_active')->default(true)->after('avatar_url');

            // Last login tracking
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            // Soft deletes
            $table->softDeletes();

            // Indexes
            $table->index('company_id');
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['is_active']);
            $table->dropIndex(['role']);
            $table->dropIndex(['company_id']);

            $table->dropColumn([
                'company_id',
                'role',
                'avatar_url',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
