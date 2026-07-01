<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserAvatarController;
use App\Http\Controllers\UserSearchController;
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
    Route::view('/chat/{room}', 'chat')->where('room', '[0-9]+');
    Route::get('/api/chat-rooms', [ChatRoomController::class, 'index']);
    Route::post('/api/chat-rooms/direct', [ChatRoomController::class, 'storeDirect']);
    Route::post('/api/chat-rooms/group', [ChatRoomController::class, 'storeGroup']);
    Route::delete('/api/chat-rooms/{room}', [ChatRoomController::class, 'destroy']);
    Route::get('/api/users/search', [UserSearchController::class, 'index']);
    Route::get('/api/users/{user}/avatar', [UserAvatarController::class, 'show'])
        ->name('users.avatar');
    Route::get('/api/profile', [ProfileController::class, 'show']);
    Route::post('/api/profile', [ProfileController::class, 'update']);
    Route::get('/api/messages', [MessageController::class, 'index']);
    Route::post('/api/messages', [MessageController::class, 'store']);
    Route::delete('/api/messages/{message}', [MessageController::class, 'destroy']);
    Route::get('/api/chat/files/{attachment}/{token}', [ChatAttachmentController::class, 'show'])
        ->name('chat.files.show');
});
