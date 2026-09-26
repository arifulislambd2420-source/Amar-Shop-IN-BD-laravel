<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('image', 500)->default('');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            // PIM columns (added after initial release in the old schema)
            $table->string('sku', 64)->nullable();
            $table->string('status', 20)->default('published'); // draft/published/hidden/outofstock/archived
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('tags', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique on sku allows multiple NULLs on SQLite/MySQL, so blank SKUs don't collide.
            $table->unique('sku', 'idx_products_sku');
            $table->index('status', 'idx_products_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
