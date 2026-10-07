<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            // Which resources/views/landing/templates/{template}.blade.php
            // renders this page — see LandingPage::TEMPLATE_OPTIONS.
            $table->string('template')->default('template-1');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('hero_image', 500)->nullable();
            $table->string('headline');
            $table->string('sub_headline')->nullable();
            $table->text('description')->nullable();
            // List of image URLs (strings).
            $table->json('gallery')->nullable();
            // List of {title, description} feature entries.
            $table->json('features')->nullable();
            // Optional per-page price, overriding the linked product's price
            // (e.g. a landing-page-only promo price). Still server-side data,
            // never trusted from the client at order time.
            $table->decimal('price_override', 10, 2)->nullable();
            $table->string('button_text')->default('এখনই অর্ডার করুন');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index('is_active', 'idx_landing_pages_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
