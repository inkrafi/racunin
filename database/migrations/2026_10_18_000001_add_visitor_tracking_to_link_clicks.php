<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Daily-rotating, salted visitor fingerprint (no raw IP is stored)
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->string('visitor_hash', 64)->nullable()->after('ip_address');
        });

        // One row per (link, day, visitor): an atomic "first time today?" check
        // that keeps link_click_summaries.unique_clicks race-free.
        Schema::create('link_unique_visitors', function (Blueprint $table) {
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->date('date');
            $table->string('visitor_hash', 64);

            $table->primary(['link_id', 'date', 'visitor_hash']);
            $table->index('date', 'idx_luv_date');
        });

        // jsonb allows atomic per-channel increments inside the summary upsert
        DB::statement('ALTER TABLE link_click_summaries ALTER COLUMN channel_breakdown TYPE jsonb USING channel_breakdown::jsonb');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE link_click_summaries ALTER COLUMN channel_breakdown TYPE json USING channel_breakdown::json');

        Schema::dropIfExists('link_unique_visitors');

        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropColumn('visitor_hash');
        });
    }
};
