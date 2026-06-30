import { readFileSync, writeFileSync, existsSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const target = join(root, 'node_modules/laravel-echo-server/dist/channels/private-channel.js');

if (!existsSync(target)) {
    process.exit(0);
}

const marker = "options.headers['Authorization']";
let source = readFileSync(target, 'utf8');

if (source.includes(marker)) {
    process.exit(0);
}

const needle = "options.headers['X-Requested-With'] = 'XMLHttpRequest';";
const patch = `${needle}
        if (socket.request.headers.authorization) {
            options.headers['Authorization'] = socket.request.headers.authorization;
        }`;

if (!source.includes(needle)) {
    console.error('patch-echo-server: unexpected private-channel.js format');
    process.exit(1);
}

writeFileSync(target, source.replace(needle, patch));
console.log('patch-echo-server: Authorization header forwarding enabled');
