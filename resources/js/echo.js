import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.io = io;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const echoPort = import.meta.env.VITE_ECHO_SERVER_PORT ?? '6001';
const useNginxProxy = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: useNginxProxy
        ? window.location.hostname
        : `${window.location.hostname}:${echoPort}`,
    ...(useNginxProxy ? { secure: true } : {}),
    auth: {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
        },
    },
});
