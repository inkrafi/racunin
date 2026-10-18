<?php

use App\Enums\LinkStatus;
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
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('title');
            $table->text('original_url');
            $table->string('slug')->unique()->index();
            $table->string('status')->default(LinkStatus::ACTIVE->value)->index();
            $table->text('description')->nullable();
            $table->timestamps();

            // Composite index for fast tenant query
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
