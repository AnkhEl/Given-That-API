<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a super admin (not tied to any company)
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@giventhat.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');

        // Create a demo company
        $company = Company::create([
            'name' => 'TechCorp Solutions',
            'slug' => 'techcorp-solutions',
            'contact_email' => 'contact@techcorp.com',
            'contact_phone' => '+1-555-0123',
            'subscription_tier' => 'professional',
            'subscription_status' => 'active',
            'max_learners' => 50,
            'max_courses_per_month' => 20,
            'subscription_starts_at' => now(),
            'subscription_ends_at' => now()->addYear(),
        ]);

        // Create company admin for TechCorp
        $companyAdmin = User::create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah@techcorp.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $companyAdmin->assignRole('company_admin');

        // Create some trainees for TechCorp
        $trainees = [
            ['name' => 'Marcus Anderson', 'email' => 'marcus@techcorp.com'],
            ['name' => 'Emily Chen', 'email' => 'emily@techcorp.com'],
            ['name' => 'David Kumar', 'email' => 'david@techcorp.com'],
            ['name' => 'Lisa Martinez', 'email' => 'lisa@techcorp.com'],
            ['name' => 'James Wilson', 'email' => 'james@techcorp.com'],
        ];

        foreach ($trainees as $traineeData) {
            $trainee = User::create([
                'name' => $traineeData['name'],
                'email' => $traineeData['email'],
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'role' => 'trainee',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $trainee->assignRole('trainee');
        }

        // Create a curator (internal Given That employee)
        $curator = User::create([
            'name' => 'Dr. Elena Rodriguez',
            'email' => 'elena@giventhat.com',
            'password' => Hash::make('password'),
            'role' => 'curator',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $curator->assignRole('curator');

        // Create another demo company
        $company2 = Company::create([
            'name' => 'Digital Innovations Inc',
            'slug' => 'digital-innovations',
            'contact_email' => 'info@digitalinnovations.com',
            'subscription_tier' => 'starter',
            'subscription_status' => 'trial',
            'max_learners' => 10,
            'max_courses_per_month' => 5,
            'subscription_starts_at' => now(),
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $companyAdmin2 = User::create([
            'name' => 'Michael Roberts',
            'email' => 'michael@digitalinnovations.com',
            'password' => Hash::make('password'),
            'company_id' => $company2->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $companyAdmin2->assignRole('company_admin');

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('Login Credentials:');
        $this->command->info('==================');
        $this->command->info('Super Admin: admin@giventhat.com / password');
        $this->command->info('Company Admin (TechCorp): sarah@techcorp.com / password');
        $this->command->info('Trainee (TechCorp): marcus@techcorp.com / password');
        $this->command->info('Curator: elena@giventhat.com / password');
        $this->command->info('');
    }
}
