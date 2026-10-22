<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('combinable')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->string('segment', 30)->default('all');
            $table->foreignId('price_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 30)->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
        });
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('discount');
            $table->timestamps();
            $table->unique(['coupon_id', 'order_id']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->json('coupon_snapshot')->nullable()->after('coupon_code'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('coupon_snapshot'));
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_group_id');
            $table->dropColumn(['combinable', 'priority', 'segment', 'target_type', 'target_id', 'per_user_limit']);
        });
    }
};
