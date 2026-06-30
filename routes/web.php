<?php

use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/chat');
});

Route::middleware(['web', 'guest.chat'])->group(function () {
    Route::view('/chat', 'chat');
    Route::get('/api/messages', [MessageController::class, 'index']);
    Route::post('/api/messages', [MessageController::class, 'store']);
    Route::get('/api/chat/files/{attachment}/{token}', [ChatAttachmentController::class, 'show'])
        ->name('chat.files.show');
});
