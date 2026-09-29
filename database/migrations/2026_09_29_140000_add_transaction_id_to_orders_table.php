<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * orders already has payment_method/payment_status (used by COD).
     * bKash needs somewhere to record the gateway's own reference: holds
     * the paymentID right after App\Services\Payment\BkashService::
     * createPayment() (before the customer has paid anything), then gets
     * overwritten with the final trxID once App\Services\OrderService::
     * finalizeBkashPayment() confirms payment — see BkashCallbackController.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('transaction_id', 100)->nullable()->after('payment_status');
            $table->index('transaction_id', 'idx_orders_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_transaction_id');
            $table->dropColumn('transaction_id');
        });
    }
};
