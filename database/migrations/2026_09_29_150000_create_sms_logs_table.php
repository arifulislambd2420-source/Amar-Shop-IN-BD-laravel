<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (order, SMS event). The unique key is what guarantees the
     * same SMS is never sent twice for the same order — see
     * App\Services\Sms\SmsService::claim(). Also doubles as an audit trail
     * (what was sent, to whom, gateway response) and the per-phone daily cap.
     */
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20); // confirmed | shipped | delivered
            $table->string('phone', 20);
            $table->text('message');
            $table->string('status', 20)->default('pending'); // pending | sent | failed | skipped
            $table->text('response')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'event'], 'uniq_sms_logs_order_event');
            $table->index(['phone', 'created_at'], 'idx_sms_logs_phone_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
