import { readFileSync, writeFileSync, existsSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

function patchPrivateChannel() {
    const target = join(root, 'node_modules/laravel-echo-server/dist/channels/private-channel.js');

    if (!existsSync(target)) {
        return;
    }

    const marker = "options.headers['Authorization']";
    let source = readFileSync(target, 'utf8');

    if (source.includes(marker)) {
        return;
    }

    const needle = "options.headers['X-Requested-With'] = 'XMLHttpRequest';";
    const patch = `${needle}
        if (socket.request.headers.authorization) {
            options.headers['Authorization'] = socket.request.headers.authorization;
        }`;

    if (!source.includes(needle)) {
        console.error('patch-echo-server: unexpected private-channel.js format');
        return;
    }

    writeFileSync(target, source.replace(needle, patch));
    console.log('patch-echo-server: Authorization header forwarding enabled');
}

function patchPresenceChannel() {
    const target = join(root, 'node_modules/laravel-echo-server/dist/channels/presence-channel.js');

    if (!existsSync(target)) {
        return;
    }

    let source = readFileSync(target, 'utf8');

    // Fix 1: guard against undefined member when filtering on leave
    const filterNeedle = "members = members.filter(function (m) { return m.socketId != member.socketId; });";
    const filterPatch = "members = members.filter(function (m) { return m && member && m.socketId != member.socketId; });";

    if (source.includes(filterNeedle)) {
        source = source.replace(filterNeedle, filterPatch);
        console.log('patch-echo-server: presence-channel.js filter guard applied');
    }

    // Fix 2: guard against undefined member when deleting socketId on leave
    const deleteNeedle = "delete member.socketId;";
    const deletePatch = "if (member) { delete member.socketId; }";

    if (source.includes(deleteNeedle) && !source.includes(deletePatch)) {
        source = source.replace(deleteNeedle, deletePatch);
        console.log('patch-echo-server: presence-channel.js delete guard applied');
    }

    writeFileSync(target, source);
    console.log('patch-echo-server: presence-channel.js patches applied');
}

patchPrivateChannel();
patchPresenceChannel();
