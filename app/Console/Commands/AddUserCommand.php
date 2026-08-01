<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('user:add
    {username? : Username пользователя}
    {name? : Отображаемое имя}
    {--password= : Пароль (если не указан, будет запрошен интерактивно)}')]
#[Description('Добавить нового пользователя')]
class AddUserCommand extends Command
{
    public function handle(): int
    {
        $username = $this->argument('username') ?? $this->ask('Username');
        $name = $this->argument('name') ?? $this->ask('Имя', $username);
        $password = $this->option('password') ?? $this->secret('Пароль');

        $validator = Validator::make(
            compact('username', 'name', 'password'),
            [
                'username' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $user = User::query()->create($validated);

        $this->info("Пользователь создан: {$user->name} — {$user->username}");

        return self::SUCCESS;
    }
}
