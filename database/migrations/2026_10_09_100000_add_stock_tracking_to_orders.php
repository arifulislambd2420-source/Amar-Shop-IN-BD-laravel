<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Stock bookkeeping for orders:
|  - order_items.variant_id: which size/variant was sold, so its stock (not
|    just the product's) can be taken on bKash confirmation and given back
|    when an order is cancelled. Older rows stay null (product-level only).
|  - orders.stock_reserved: true while this order holds stock, so a status
|    flipped to cancelled and back never returns or takes stock twice.
|    Backfilled for existing orders whose stock was already taken: every
|    COD order, and bKash orders that were paid and not put on hold.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')->nullable()->after('product_id')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('stock_reserved')->default(false);
        });

        DB::table('orders')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'bkash')
                ->orWhere(fn ($q) => $q->where('payment_status', 'paid')->where('status', '!=', 'on_hold')))
            ->update(['stock_reserved' => true]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stock_reserved');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['variant_id']);
            $table->dropColumn('variant_id');
        });
    }
};
