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
        // Keys that commission reports share with a link
        Schema::table('links', function (Blueprint $table) {
            $table->string('sub_id', 100)->nullable()->after('slug');
            $table->string('product_ref', 64)->nullable()->after('sub_id');

            $table->index(['user_id', 'sub_id'], 'idx_links_user_sub_id');
            $table->index(['user_id', 'product_ref'], 'idx_links_user_product_ref');
        });

        Schema::create('commission_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('filename');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_matched')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_ci_user_created');
        });

        // One row per order item; re-importing a newer report updates status and commission in place
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
            $table->foreignId('commission_import_id')->nullable()->constrained('commission_imports')->nullOnDelete();
            $table->string('platform', 30);
            $table->string('order_id', 100);
            $table->string('item_key', 100)->default('');
            $table->string('item_ref', 64)->nullable();
            $table->string('product_name')->nullable();
            $table->string('sub_id', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('order_value', 14, 2)->default(0);
            $table->decimal('commission', 14, 2)->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'order_id', 'item_key'], 'uniq_conversions_order_item');
            $table->index(['user_id', 'ordered_at'], 'idx_conv_user_ordered');
            $table->index(['link_id', 'ordered_at'], 'idx_conv_link_ordered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('commission_imports');

        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex('idx_links_user_sub_id');
            $table->dropIndex('idx_links_user_product_ref');
            $table->dropColumn(['sub_id', 'product_ref']);
        });
    }
};
