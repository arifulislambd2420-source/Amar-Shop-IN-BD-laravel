<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * is_flagged / flag_reason: set by App\Services\OrderRiskService when an
     * order looks like a duplicate/fake, for an admin to review — the order
     * is still created normally. ip_address is what makes the per-IP rate
     * rule possible (it wasn't stored anywhere before).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false)->after('status');
            $table->text('flag_reason')->nullable()->after('is_flagged');
            $table->string('ip_address', 45)->nullable()->after('flag_reason'); // 45 = longest IPv6 text form

            $table->index('is_flagged', 'idx_orders_is_flagged');
            $table->index(['ip_address', 'created_at'], 'idx_orders_ip_created');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_is_flagged');
            $table->dropIndex('idx_orders_ip_created');
            $table->dropColumn(['is_flagged', 'flag_reason', 'ip_address']);
        });
    }
};
