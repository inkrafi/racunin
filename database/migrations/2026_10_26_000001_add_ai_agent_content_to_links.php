<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Agent output per link: landing page, ad copy and video scripts.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->json('ai_landing_page')->nullable()->after('ai_captions');
            $table->timestamp('lp_published_at')->nullable()->after('ai_landing_page');
            $table->json('ai_ad_copy')->nullable()->after('lp_published_at');
            $table->json('ai_video_scripts')->nullable()->after('ai_ad_copy');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['ai_landing_page', 'lp_published_at', 'ai_ad_copy', 'ai_video_scripts']);
        });
    }
};
