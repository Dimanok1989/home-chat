<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Регистрация — Домашний чат</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css'])
    </head>
    <body class="antialiased bg-gray-50">
        <div class="flex min-h-screen items-center justify-center px-4">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Домашний чат</h1>
                    <p class="mt-2 text-sm text-gray-500">Регистрация по приглашению</p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.invite.submit', ['token' => $token]) }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="username" class="mb-2 block text-sm font-medium text-gray-700">Логин</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Придумайте логин"
                        >
                    </div>

                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-gray-700">Имя</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Ваше имя"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-gray-700">Пароль</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Придумайте пароль"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium text-gray-700">Подтверждение пароля</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            placeholder="Повторите пароль"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        Зарегистрироваться
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>