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
    {--email= : Email (необязательно)}
    {--password= : Пароль (если не указан, будет запрошен интерактивно)}')]
#[Description('Добавить нового пользователя')]
class AddUserCommand extends Command
{
    public function handle(): int
    {
        $username = $this->argument('username') ?? $this->ask('Username');
        $name = $this->argument('name') ?? $this->ask('Имя', $username);
        $email = $this->resolveEmail();
        $password = $this->option('password') ?? $this->secret('Пароль');

        $validator = Validator::make(
            compact('username', 'name', 'email', 'password'),
            [
                'username' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
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

        $identity = $user->email
            ? "{$user->username} ({$user->email})"
            : $user->username;

        $this->info("Пользователь создан: {$user->name} — {$identity}");

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        if ($this->option('email') !== null) {
            $email = $this->option('email');

            return $email === '' ? null : $email;
        }

        if ($this->hasRequiredArguments()) {
            return null;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $email = $this->ask('Email (необязательно)', null);

        return $email === '' ? null : $email;
    }

    private function hasRequiredArguments(): bool
    {
        return $this->argument('username') !== null
            && $this->argument('name') !== null
            && $this->option('password') !== null;
    }
}
