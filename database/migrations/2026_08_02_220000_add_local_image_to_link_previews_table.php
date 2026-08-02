<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_previews', function (Blueprint $table) {
            $table->string('image_disk')->nullable()->after('image_url');
            $table->string('image_path')->nullable()->after('image_disk');
            $table->string('image_mime_type')->nullable()->after('image_path');
            $table->string('image_access_token', 64)->nullable()->after('image_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('link_previews', function (Blueprint $table) {
            $table->dropColumn([
                'image_disk',
                'image_path',
                'image_mime_type',
                'image_access_token',
            ]);
        });
    }
};
