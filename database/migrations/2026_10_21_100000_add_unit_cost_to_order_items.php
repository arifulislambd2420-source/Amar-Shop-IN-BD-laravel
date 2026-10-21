<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| order_items.unit_cost: what one unit cost the shop when the order was placed (the product's cost
| price at that moment), so the dashboard's profit does not change when a cost price is edited later.
| Existing lines get the product's current cost price (null where none is set).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable();
        });

        DB::statement('UPDATE order_items SET unit_cost = (SELECT cost_price FROM products WHERE products.id = order_items.product_id) WHERE unit_cost IS NULL');
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
