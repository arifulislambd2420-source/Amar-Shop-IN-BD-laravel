<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-page colors for the block-builder templates. NULL = use the
     * template's own default (LandingPage::TEMPLATE_COLORS); they are
     * independent of the site-wide brand color.
     */
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->after('template');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });
    }
};
