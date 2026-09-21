<?php

namespace Database\Seeders;

use App\Models\NumberSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Super Admin login
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        // Sample regular user
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Sales User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'status' => 'active',
            ]
        );

        // Default document numbering
        NumberSetting::firstOrCreate(
            ['document_type' => 'quotation'],
            ['prefix' => 'QUO-' . date('Y') . '-', 'postfix' => '', 'next_number' => 1, 'number_padding' => 4]
        );

        NumberSetting::firstOrCreate(
            ['document_type' => 'invoice'],
            ['prefix' => 'INV-' . date('Y') . '-', 'postfix' => '', 'next_number' => 1, 'number_padding' => 4]
        );
    }
}
