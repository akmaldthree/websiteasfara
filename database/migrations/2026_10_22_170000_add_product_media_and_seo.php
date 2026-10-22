<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
        });
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('color', 80)->nullable();
            $table->string('image');
            $table->string('alt_text', 200);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
        Schema::table('series', fn (Blueprint $table) => $table->dropColumn(['seo_title', 'seo_description']));
    }
};
