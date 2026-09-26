<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('discount_type', 20)->default('percent'); // percent/fixed
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_spend', 10, 2)->default(0);
            $table->integer('uses')->default(0);
            $table->integer('max_uses')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
