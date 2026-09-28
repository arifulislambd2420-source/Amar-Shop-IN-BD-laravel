<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's default Authenticatable trait (used by AdminUser) reads
        // and writes a `remember_token` column on every login()/logout()
        // call (for the "remember me" cookie), regardless of whether the
        // checkbox was ticked. The original admin_users migration omitted
        // it, so logging in threw: SQLSTATE[42S22]: Column not found: 1054
        // Unknown column 'remember_token' in 'SET'.
        Schema::table('admin_users', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
