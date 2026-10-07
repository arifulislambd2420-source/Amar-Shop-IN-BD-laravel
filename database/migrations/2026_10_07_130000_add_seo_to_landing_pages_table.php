<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per-landing-page SEO: browser/search title, meta description, social image. */
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('og_image', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'og_image']);
        });
    }
};
