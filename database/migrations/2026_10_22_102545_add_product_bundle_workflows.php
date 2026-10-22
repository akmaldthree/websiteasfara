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
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_variant_id')->constrained('variants')->cascadeOnDelete();
            $table->foreignId('component_variant_id')->constrained('variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['bundle_variant_id', 'component_variant_id']);
        });
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
        Schema::dropIfExists('bundle_components');
    }
};
