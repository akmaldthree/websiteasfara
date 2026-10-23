<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_original_cost')->nullable();
            $table->string('shipping_override_reason', 500)->nullable();
            $table->foreignId('shipping_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipping_overridden_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_overridden_by');
            $table->dropColumn(['shipping_original_cost', 'shipping_override_reason', 'shipping_overridden_at']);
        });
    }
};
