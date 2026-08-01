<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Alice Johnson',
            'username' => 'alice',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Bob Smith',
            'username' => 'bob',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Charlie Brown',
            'username' => 'charlie',
            'password' => bcrypt('password'),
        ]);
    }
}
