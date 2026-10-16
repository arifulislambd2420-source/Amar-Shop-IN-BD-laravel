<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Admin users get a display name, an optional (unique) e-mail and an active
| switch. Sign-in still uses the username (an e-mail works too); an inactive
| admin cannot sign in or open the panel. Existing admins stay active.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['name', 'email', 'is_active']);
        });
    }
};
