<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| What the browser knew when an order was placed, kept on the order so a server-side
| Purchase (Meta Conversions API) can be sent for it later: the Pixel's _fbp / _fbc
| cookies, the browser (user agent), the page the order was placed on and the Google
| Analytics client id. The visitor's IP is already in orders.ip_address.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();
            $table->string('client_user_agent', 512)->nullable();
            $table->string('event_source_url', 2000)->nullable();
            $table->string('ga_client_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fbp', 'fbc', 'client_user_agent', 'event_source_url', 'ga_client_id']);
        });
    }
};
