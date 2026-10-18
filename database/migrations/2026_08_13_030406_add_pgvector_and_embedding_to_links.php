<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasVector = DB::select("SELECT 1 FROM pg_available_extensions WHERE name = 'vector'");

        if (!empty($hasVector)) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE links ADD COLUMN IF NOT EXISTS embedding vector(768)');
        } else {
            // Fallback for local development environments where pgvector extension binary is not compiled
            Schema::table('links', function (Blueprint $table) {
                $table->jsonb('embedding')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            if (Schema::hasColumn('links', 'embedding')) {
                $table->dropColumn('embedding');
            }
        });
    }
};
