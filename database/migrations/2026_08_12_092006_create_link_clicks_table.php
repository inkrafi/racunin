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
        Schema::create('link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('clicked_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('referrer')->nullable();
            $table->string('referrer_channel', 50)->default('direct');
            $table->string('device_type', 30)->default('unknown');
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for fast lookup
            $table->index('clicked_at', 'idx_lc_clicked_at');
            $table->index('referrer_channel', 'idx_lc_channel');
            $table->index('device_type', 'idx_lc_device');
            $table->index(['user_id', 'clicked_at'], 'idx_lc_user_clicked');
            $table->index(['link_id', 'clicked_at'], 'idx_lc_link_clicked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
    }
};
