<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Company management
            'manage-companies',
            'view-companies',
            'edit-company',

            // Team management
            'manage-team',
            'invite-team-members',
            'remove-team-members',
            'view-team-progress',

            // Skill metric files
            'upload-skill-metrics',
            'view-skill-metrics',
            'delete-skill-metrics',

            // Course management
            'manage-courses',
            'create-courses',
            'edit-courses',
            'publish-courses',
            'delete-courses',
            'view-all-courses',

            // Course curation
            'curate-courses',
            'review-ai-generated-courses',

            // Enrollment
            'enroll-users',
            'unenroll-users',
            'view-enrollments',

            // Learning
            'view-courses',
            'enroll-in-courses',
            'access-course-content',
            'submit-assessments',
            'view-own-progress',

            // Assessments
            'manage-assessments',
            'create-assessments',
            'grade-assessments',

            // Certificates
            'issue-certificates',
            'view-certificates',

            // Analytics & Reports
            'view-analytics',
            'view-company-analytics',
            'export-reports',

            // Skills management
            'manage-skills',
            'create-skills',
            'edit-skills',

            // System
            'manage-system',
            'view-system-logs',
            'manage-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - All permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Company Admin - Company and team management
        $companyAdmin = Role::firstOrCreate(['name' => 'company_admin']);
        $companyAdmin->givePermissionTo([
            'view-companies',
            'edit-company',
            'manage-team',
            'invite-team-members',
            'remove-team-members',
            'view-team-progress',
            'upload-skill-metrics',
            'view-skill-metrics',
            'enroll-users',
            'unenroll-users',
            'view-enrollments',
            'view-company-analytics',
            'export-reports',
            'view-certificates',
        ]);

        // Curator - Course creation and management
        $curator = Role::firstOrCreate(['name' => 'curator']);
        $curator->givePermissionTo([
            'create-courses',
            'edit-courses',
            'publish-courses',
            'curate-courses',
            'review-ai-generated-courses',
            'view-all-courses',
            'manage-assessments',
            'create-assessments',
            'grade-assessments',
            'view-analytics',
        ]);

        // Trainee - Learning access only
        $trainee = Role::firstOrCreate(['name' => 'trainee']);
        $trainee->givePermissionTo([
            'view-courses',
            'enroll-in-courses',
            'access-course-content',
            'submit-assessments',
            'view-own-progress',
            'view-certificates',
        ]);

        $this->command->info('Roles and permissions seeded successfully!');
    }
}
