<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $images = [
            'editorial-fabrics-3x2',
            'hero-family-2x1',
            'look-olive-beige-4x5',
            'payment-proof-demo-3x4',
            'product-black-4x5',
            'product-family-sage-4x5',
            'product-olive-4x5',
            'product-rose-4x5',
            'promo-taupe-3x4',
        ];

        foreach ($images as $image) {
            $oldPath = 'demo/'.$image.'.png';
            $newPath = 'demo/'.$image.'.webp';
            $source = database_path('seeders/assets/'.$newPath);

            if (is_file($source)) {
                Storage::disk('public')->put($newPath, file_get_contents($source));
                Storage::disk('public')->delete($oldPath);
            }

            foreach (['series', 'product_media', 'content_entries', 'curated_links'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('image', $oldPath)->update(['image' => $newPath]);
                }
            }

            if (Schema::hasTable('payment_proofs')) {
                DB::table('payment_proofs')->where('path', $oldPath)->update(['path' => $newPath]);
            }

            if (Schema::hasTable('media_assets')) {
                $attributes = ['path' => $newPath, 'mime' => 'image/webp'];
                if (Storage::disk('public')->exists($newPath)) {
                    $attributes['size'] = Storage::disk('public')->size($newPath);
                }

                DB::table('media_assets')->where('path', $oldPath)->update($attributes);
            }
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'hero_image')
                ->where('value', json_encode('demo/hero-family-2x1.png'))
                ->update(['value' => json_encode('demo/hero-family-2x1.webp')]);
        }
    }

    public function down(): void
    {
        // WebP remains valid after rollback; reverting references would point to removed PNG files.
    }
};
