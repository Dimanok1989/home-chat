<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::statement('ALTER TABLE sessions MODIFY user_id VARCHAR(36) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::statement('ALTER TABLE sessions MODIFY user_id BIGINT UNSIGNED NULL');
    }
};
