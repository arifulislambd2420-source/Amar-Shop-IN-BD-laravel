<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('customer_name');
            $table->integer('rating'); // old schema enforces CHECK (rating BETWEEN 1 AND 5) at app level
            $table->text('comment')->nullable();
            $table->boolean('approved')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('approved', 'idx_reviews_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
