<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'guest.chat']]);

Broadcast::channel('chat', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
