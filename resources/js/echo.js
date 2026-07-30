import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.io = io;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: `${window.location.hostname}:${window.location.port}`,
    auth: {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
        },
    },
});
