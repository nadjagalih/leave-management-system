<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'leave_quota' => 99, // Admin has unlimited quota
            ]);
        }

        // Create a few Employee Users
        $employeeRole = Role::where('name', 'Employee')->first();
        if ($employeeRole) {
            User::factory()->count(5)->create([
                'role_id' => $employeeRole->id,
            ]);
        }
    }
}
