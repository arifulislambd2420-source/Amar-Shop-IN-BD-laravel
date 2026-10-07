<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Why the last queued courier dispatch failed (shown on the admin order page). */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('courier_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('courier_error');
        });
    }
};
