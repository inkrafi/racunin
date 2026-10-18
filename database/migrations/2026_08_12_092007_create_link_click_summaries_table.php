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
        Schema::create('link_click_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('unique_clicks')->default(0);
            $table->unsignedInteger('mobile_clicks')->default(0);
            $table->unsignedInteger('desktop_clicks')->default(0);
            $table->unsignedInteger('tablet_clicks')->default(0);
            $table->string('top_channel', 50)->nullable();
            $table->json('channel_breakdown')->nullable();
            $table->timestamps();

            // Indexes & Unique constraints
            $table->unique(['link_id', 'date'], 'uniq_lcs_link_date');
            $table->index(['user_id', 'date'], 'idx_lcs_user_date');
            $table->index('date', 'idx_lcs_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_click_summaries');
    }
};
