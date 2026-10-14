<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Marks the sample landing pages added by DemoLandingSeeder so
| `php artisan demo:remove` can delete exactly those. Additive and nullable-safe:
| every existing page gets is_demo = false.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
