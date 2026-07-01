<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-id" content="{{ auth()->id() }}">
        <meta name="user-name" content="{{ auth()->user()->name }}">

        <title>Домашний чат</title>

        <script>
            (function () {
                const stored = localStorage.getItem('chat-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const dark = stored === 'dark' || (stored !== 'light' && prefersDark);

                if (dark) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
