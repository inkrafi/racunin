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
        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique()->index();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('theme')->default('orange'); // orange, indigo, emerald, slate
            $table->json('social_links')->nullable(); // instagram, tiktok, whatsapp, youtube, telegram
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};
