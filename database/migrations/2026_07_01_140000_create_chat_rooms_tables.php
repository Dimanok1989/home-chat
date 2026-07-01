<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name')->nullable();
            $table->string('direct_hash')->nullable()->unique();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('last_message_at');
        });

        Schema::create('chat_room_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_room_id', 'user_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('chat_room_id')->nullable()->after('user_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->index(['chat_room_id', 'id']);
        });

        $globalRoomId = DB::table('chat_rooms')->insertGetId([
            'type' => 'global',
            'name' => 'Общий чат',
            'direct_hash' => null,
            'last_message_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('messages')->update(['chat_room_id' => $globalRoomId]);

        $lastMessageAt = DB::table('messages')
            ->where('chat_room_id', $globalRoomId)
            ->max('created_at');

        if ($lastMessageAt !== null) {
            DB::table('chat_rooms')
                ->where('id', $globalRoomId)
                ->update(['last_message_at' => $lastMessageAt]);
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('chat_room_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['chat_room_id']);
            $table->dropIndex(['chat_room_id', 'id']);
            $table->dropColumn('chat_room_id');
        });

        Schema::dropIfExists('chat_room_user');
        Schema::dropIfExists('chat_rooms');
    }
};
