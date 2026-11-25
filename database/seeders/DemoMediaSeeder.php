<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoMediaSeeder extends Seeder
{
    public const HERO_DESKTOP = 'demo/hero-desktop.webm';

    public const HERO_MOBILE = 'demo/hero-mobile.webm';

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (glob(database_path('seeders/assets/demo/*.*')) ?: [] as $source) {
            if (! in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['png', 'webp', 'webm'], true)) {
                continue;
            }

            $contents = file_get_contents($source);
            $destination = 'demo/'.basename($source);
            if ($contents === false || ! $disk->put($destination, $contents)) {
                throw new RuntimeException('Gagal memasang aset demo: '.$destination);
            }
        }
    }
}
