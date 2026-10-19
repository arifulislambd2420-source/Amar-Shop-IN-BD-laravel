<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Automatic blocks: an IP that sends an abnormal number of requests is blocked for a
| while. is_auto marks those rows, expires_at ends the block (null = until an admin
| removes it, as every manual block always was), hits is the count that triggered it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ip_blocks', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('hits')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ip_blocks', function (Blueprint $table) {
            $table->dropColumn(['is_auto', 'expires_at', 'hits']);
        });
    }
};
