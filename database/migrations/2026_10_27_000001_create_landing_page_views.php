<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily visits to AI landing pages, so their buy clicks can be shown as a conversion rate.
     */
    public function up(): void
    {
        Schema::create('landing_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_views')->default(0);
            $table->timestamps();

            $table->unique(['link_id', 'date'], 'uq_lpv_link_date');
        });

        Schema::create('landing_page_visitors', function (Blueprint $table) {
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->date('date');
            $table->string('visitor_hash', 64);

            $table->primary(['link_id', 'date', 'visitor_hash']);
            $table->index('date', 'idx_lpvis_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_visitors');
        Schema::dropIfExists('landing_page_views');
    }
};
