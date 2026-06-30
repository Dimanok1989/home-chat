<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::view('/chat', 'chat');
    Route::get('/api/messages', [MessageController::class, 'index']);
    Route::post('/api/messages', [MessageController::class, 'store']);
    Route::delete('/api/messages/{message}', [MessageController::class, 'destroy']);
    Route::get('/api/chat/files/{attachment}/{token}', [ChatAttachmentController::class, 'show'])
        ->name('chat.files.show');
});
