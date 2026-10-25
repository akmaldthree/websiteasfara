<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', fn (Blueprint $table) => $table->json('size_chart')->nullable()->after('size_guide'));
        Schema::create('curated_links', function (Blueprint $table) {
            $table->id();
            $table->string('section', 30)->default('fit');
            $table->string('title');
            $table->string('url', 500);
            $table->string('eyebrow')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curated_links');
        Schema::table('series', fn (Blueprint $table) => $table->dropColumn('size_chart'));
    }
};
