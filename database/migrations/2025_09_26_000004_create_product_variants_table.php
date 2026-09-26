<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('label', 100);
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
