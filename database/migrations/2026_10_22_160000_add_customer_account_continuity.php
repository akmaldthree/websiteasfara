<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_whatsapp')->default(false);
            $table->timestamp('consent_updated_at')->nullable();
        });
        Schema::create('user_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->json('customization')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'variant_id']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->timestamp('claimed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('claimed_at'));
        Schema::dropIfExists('user_cart_items');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['marketing_email', 'marketing_whatsapp', 'consent_updated_at']));
    }
};
