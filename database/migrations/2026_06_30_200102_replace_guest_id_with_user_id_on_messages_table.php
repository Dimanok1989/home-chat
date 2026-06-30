<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['guest_id', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('guest_id')->after('id');
            $table->string('ip_address', 45)->after('guest_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
