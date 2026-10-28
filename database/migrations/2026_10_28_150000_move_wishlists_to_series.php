<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('user_id')->constrained('series')->cascadeOnDelete();
        });

        $seen = [];

        DB::table('wishlist_items')->orderBy('id')->get()->each(function ($item) use (&$seen) {
            $seriesId = DB::table('variants')->where('id', $item->variant_id)->value('series_id');

            if (! $seriesId) {
                DB::table('wishlist_items')->where('id', $item->id)->delete();
                return;
            }

            $key = $item->user_id.':'.$seriesId;
            if (isset($seen[$key])) {
                DB::table('wishlist_items')->where('id', $item->id)->delete();
                return;
            }

            $seen[$key] = true;
            DB::table('wishlist_items')->where('id', $item->id)->update(['series_id' => $seriesId]);
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unique(['user_id', 'series_id']);
            $table->dropUnique(['user_id', 'variant_id']);
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('series_id')->constrained('variants')->cascadeOnDelete();
        });

        DB::table('wishlist_items')->orderBy('id')->get()->each(function ($item) {
            $variantId = DB::table('variants')->where('series_id', $item->series_id)->orderBy('id')->value('id');
            DB::table('wishlist_items')->where('id', $item->id)->update(['variant_id' => $variantId]);
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unique(['user_id', 'variant_id']);
            $table->dropUnique(['user_id', 'series_id']);
            $table->dropForeign(['series_id']);
            $table->dropColumn('series_id');
        });
    }
};
