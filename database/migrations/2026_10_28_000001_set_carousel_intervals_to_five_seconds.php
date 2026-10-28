<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (['hero_autoplay_seconds', 'announcement_interval_seconds'] as $key) {
            $setting = DB::table('settings')->where('key', $key);

            if ($setting->exists()) {
                $setting->update(['value' => json_encode(5), 'updated_at' => now()]);
            } else {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => json_encode(5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (['hero_autoplay_seconds' => 6.5, 'announcement_interval_seconds' => 4] as $key => $value) {
            DB::table('settings')->where('key', $key)->update([
                'value' => json_encode($value),
                'updated_at' => now(),
            ]);
        }
    }
};
