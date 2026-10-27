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
        // Ad attribution per click; reports by creative query the raw log by date range
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->after('referrer_channel');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->string('utm_content', 100)->nullable()->after('utm_campaign');

            $table->index(['user_id', 'utm_content', 'clicked_at'], 'idx_lc_user_utm_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex('idx_lc_user_utm_content');
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content']);
        });
    }
};
