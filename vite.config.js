import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        },
        watch: {
            // Avoid ENOSPC when inotify max_user_watches is exhausted on Linux.
            usePolling: true,
            interval: 1000,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
