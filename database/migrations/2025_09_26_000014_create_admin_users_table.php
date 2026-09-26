<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate admin table (NOT merged into users). Filament admin-guard
        // wiring happens in a later phase. `password` uses Laravel bcrypt Hash;
        // old bcrypt `password_hash` values are compatible and can be copied later.
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role', 50)->default('super_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
