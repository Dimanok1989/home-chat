#!/bin/sh
set -e

# Patch presence-channel.js to guard against undefined member on leave
PRESENCE_CHANNEL="/usr/local/lib/node_modules/laravel-echo-server/dist/channels/presence-channel.js"
if [ -f "$PRESENCE_CHANNEL" ]; then
    # Fix: members.filter(function (m) { return m.socketId != member.socketId; })
    # Guard against undefined 'm' and undefined 'member' when member is already removed
    sed -i 's/return m\.socketId != member\.socketId/return m \&\& member \&\& m.socketId != member.socketId/' "$PRESENCE_CHANNEL"
    echo "Patched presence-channel.js"
fi

exec laravel-echo-server start