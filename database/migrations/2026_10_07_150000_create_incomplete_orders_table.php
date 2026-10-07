<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visitors who typed a phone number on the checkout / a landing page but
     * did not place the order. Admins can call them and convert the row into
     * a real order ("Incomplete Orders" in the admin).
     */
    public function up(): void
    {
        Schema::create('incomplete_orders', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->index();
            $table->string('source', 20)->default('checkout'); // checkout | landing
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 30)->index();
            $table->string('district', 100)->nullable();
            $table->string('address', 1000)->nullable();
            // [{product_id, variant_id, name, quantity, unit_price}] — what they were about to buy.
            $table->json('items')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status', 20)->default('open'); // open | converted | dismissed
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomplete_orders');
    }
};
