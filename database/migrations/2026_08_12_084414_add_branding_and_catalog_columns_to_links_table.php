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
        Schema::table('links', function (Blueprint $table) {
            // Open Graph Branding fields
            $table->string('og_title')->nullable()->after('description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->text('og_image_url')->nullable()->after('og_description');

            // Catalog & Product Showcase fields
            $table->decimal('price', 12, 2)->nullable()->after('og_image_url');
            $table->string('discount_tag')->nullable()->after('price'); // e.g. "Diskon 50%", "Flash Sale"
            $table->string('category')->nullable()->after('discount_tag')->index();
            $table->boolean('is_in_catalog')->default(true)->after('category')->index();
            $table->boolean('is_featured')->default(false)->after('is_in_catalog')->index();
            $table->integer('sort_order')->default(0)->after('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn([
                'og_title',
                'og_description',
                'og_image_url',
                'price',
                'discount_tag',
                'category',
                'is_in_catalog',
                'is_featured',
                'sort_order',
            ]);
        });
    }
};
