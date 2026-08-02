<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('link_preview_id')
                ->nullable()
                ->after('reply_to_id')
                ->constrained('link_previews')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('link_preview_id');
        });

        Schema::dropIfExists('link_previews');
    }
};
