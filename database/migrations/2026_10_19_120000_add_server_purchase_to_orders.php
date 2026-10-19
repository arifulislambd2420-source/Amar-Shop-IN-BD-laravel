<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Server-side Purchase (Meta Conversions API + GA4 Measurement Protocol), per order:
|  - purchase_triggered_at: when the order reached the Purchase status; it is the event
|    time on every (re)try, and the "send only once" marker.
|  - capi_* / ga4_*: how each platform went — status (sent / failed), the reason when it
|    failed (never contains a token or secret) and when it was sent.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('purchase_triggered_at')->nullable();
            $table->string('capi_status', 20)->nullable();
            $table->text('capi_error')->nullable();
            $table->timestamp('capi_sent_at')->nullable();
            $table->string('ga4_status', 20)->nullable();
            $table->text('ga4_error')->nullable();
            $table->timestamp('ga4_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['purchase_triggered_at', 'capi_status', 'capi_error', 'capi_sent_at', 'ga4_status', 'ga4_error', 'ga4_sent_at']);
        });
    }
};
