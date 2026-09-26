<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('api_url', 500);
            $table->string('api_key', 500);
            $table->boolean('active')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_api_configs');
    }
};
