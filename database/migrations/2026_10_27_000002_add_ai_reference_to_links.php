<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Text read from the affiliate's reference links, shared by the landing page,
     * ad copy and video script generators so the pages aren't fetched again for each.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->json('ai_reference')->nullable()->after('ai_video_scripts');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('ai_reference');
        });
    }
};
