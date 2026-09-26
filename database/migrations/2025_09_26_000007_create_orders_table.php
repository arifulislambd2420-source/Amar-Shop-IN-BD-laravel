<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_token', 64)->nullable()->unique();
            $table->string('invoice_no', 50)->nullable()->unique();
            $table->string('customer_name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('district');
            $table->string('thana');
            $table->string('postcode', 32)->nullable();
            $table->text('address');
            $table->string('payment_method', 32)->default('cod');
            $table->string('payment_status', 50)->default('unpaid');
            $table->decimal('advance_amount', 10, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->string('consignment_id', 100)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->string('courier_status', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('phone', 'idx_orders_phone');
            $table->index('order_token', 'idx_orders_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
