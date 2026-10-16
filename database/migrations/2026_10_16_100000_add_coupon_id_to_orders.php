<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| orders.coupon_id: which coupon an order used, so deleting a live order can
| give that coupon use back. Older orders stay null (their coupon is unknown,
| so deleting them leaves the coupon's count alone). A plain indexed column,
| not a foreign key: deleting a coupon must never be blocked by old orders.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('coupon_id');
        });
    }
};
