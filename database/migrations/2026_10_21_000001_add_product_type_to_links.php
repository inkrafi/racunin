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
        // Marketplace vs digital product: digital links match conversions on Sub ID only
        Schema::table('links', function (Blueprint $table) {
            $table->string('product_type', 20)->default('marketplace')->after('product_ref')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
    }
};
