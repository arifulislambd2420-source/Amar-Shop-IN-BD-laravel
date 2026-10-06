<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Block-builder landing pages. Purely additive: the old columns (headline,
     * hero_image, gallery, features, …) are untouched, so existing pages on
     * the legacy templates (template-1/2/3) keep rendering exactly as before.
     */
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Ordered list of {type, data} blocks (Filament Builder state);
            // each block's data has a boolean "hidden".
            $table->json('blocks')->nullable()->after('features');
            // List of {product_id, label, image, price, compare_price, quantity}.
            $table->json('packages')->nullable()->after('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['blocks', 'packages']);
        });
    }
};
