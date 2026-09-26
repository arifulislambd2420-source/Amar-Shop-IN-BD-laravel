<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover', 500)->nullable();
            $table->string('category')->nullable();
            $table->text('content');
            $table->integer('read_time')->nullable();
            $table->timestamp('published_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
