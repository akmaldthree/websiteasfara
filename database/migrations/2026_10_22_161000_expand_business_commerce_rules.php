<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_groups', fn (Blueprint $table) => $table->unsignedTinyInteger('discount_percent')->default(0));
        Schema::table('business_accounts', fn (Blueprint $table) => $table->timestamp('expires_at')->nullable());
        Schema::create('business_order_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_group_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->unsignedBigInteger('minimum_amount')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['price_group_id', 'target_type', 'target_id']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->json('dropship_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('dropship_snapshot'));
        Schema::dropIfExists('business_order_rules');
        Schema::table('business_accounts', fn (Blueprint $table) => $table->dropColumn('expires_at'));
        Schema::table('price_groups', fn (Blueprint $table) => $table->dropColumn('discount_percent'));
    }
};
