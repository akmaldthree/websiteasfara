<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceUnmodifiedPalette([
            'theme_palette_base' => ['#F9F8F6', '#F4F4F2'],
            'theme_palette_surface' => ['#EFE9E3', '#FFFFFF'],
            'theme_palette_structure' => ['#D9CFC7', '#DEDED9'],
            'theme_palette_accent' => ['#C9B59C', '#171717'],
            'theme_text_color' => ['#302D2A', '#171717'],
        ]);
    }

    public function down(): void
    {
        $this->replaceUnmodifiedPalette([
            'theme_palette_base' => ['#F4F4F2', '#F9F8F6'],
            'theme_palette_surface' => ['#FFFFFF', '#EFE9E3'],
            'theme_palette_structure' => ['#DEDED9', '#D9CFC7'],
            'theme_palette_accent' => ['#171717', '#C9B59C'],
            'theme_text_color' => ['#171717', '#302D2A'],
        ]);
    }

    /**
     * Update only the original palette values so administrator customizations
     * remain untouched during deployment.
     *
     * @param  array<string, array{0: string, 1: string}>  $palette
     */
    private function replaceUnmodifiedPalette(array $palette): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($palette as $key => [$original, $replacement]) {
            $setting = DB::table('settings')->where('key', $key)->first();
            if ($setting === null || json_decode($setting->value, true) !== $original) {
                continue;
            }

            DB::table('settings')->where('key', $key)->update([
                'value' => json_encode($replacement),
                'updated_at' => now(),
            ]);
        }
    }
};
