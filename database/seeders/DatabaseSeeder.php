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
        $users = [
            ['username' => 'testuser', 'name' => 'Test User'],
            ['username' => 'alice', 'name' => 'Alice Johnson'],
            ['username' => 'bob', 'name' => 'Bob Smith'],
            ['username' => 'charlie', 'name' => 'Charlie Brown'],
        ];

        foreach ($users as $user) {
            User::query()->firstOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'password' => bcrypt('password'),
                ],
            );
        }
    }
}
