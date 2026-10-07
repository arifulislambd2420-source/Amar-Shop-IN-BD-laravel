<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saved delivery addresses of logged-in customers (used to pre-fill checkout). */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 60)->default('বাসা');
            $table->string('name');
            $table->string('phone', 30);
            $table->string('district', 100);
            $table->string('thana', 100);
            $table->string('postcode', 20)->nullable();
            $table->string('address', 1000);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
