<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| SHA-256 of each stored picture, so the same picture uploaded twice is stored
| once (see App\Support\MediaDedupe). Nullable and additive: older rows get
| their hash lazily or from `php artisan media:dedupe`.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
