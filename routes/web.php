<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LinkPreviewImageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserAvatarController;
use App\Http\Controllers\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/invite/{token}', [InvitationController::class, 'showRegistrationForm'])
        ->name('register.invite');
    Route::post('/invite/{token}', [InvitationController::class, 'register'])
        ->name('register.invite.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::view('/chat', 'chat');
    Route::view('/chat/{room}', 'chat')->where('room', '[0-9]+');
    Route::view('/call', 'chat');
    Route::get('/api/chat-rooms', [ChatRoomController::class, 'index']);
    Route::post('/api/chat-rooms/direct', [ChatRoomController::class, 'storeDirect']);
    Route::post('/api/chat-rooms/group', [ChatRoomController::class, 'storeGroup']);
    Route::delete('/api/chat-rooms/{room}', [ChatRoomController::class, 'destroy']);
    Route::post('/api/chat-rooms/{room}/read', [ChatRoomController::class, 'markRead']);
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
    Route::get('/api/chat/link-previews/{linkPreview}/image/{token}', [LinkPreviewImageController::class, 'show'])
        ->name('chat.link-previews.image');
    Route::post('/api/call/signal', [CallController::class, 'signal']);
    Route::post('/api/call/history', [CallController::class, 'history']);

    // Invitation management
    Route::get('/api/invitations', [InvitationController::class, 'indexTokens']);
    Route::post('/api/invitations', [InvitationController::class, 'store']);
});
