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

        $query = DB::table('settings')->where('key', 'theme_font');

        if ($query->exists()) {
            $query->update(['value' => json_encode('poppins'), 'updated_at' => now()]);

            return;
        }

        DB::table('settings')->insert([
            'key' => 'theme_font',
            'value' => json_encode('poppins'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $setting = DB::table('settings')->where('key', 'theme_font')->first();

        if ($setting && json_decode($setting->value, true) === 'poppins') {
            DB::table('settings')->where('key', 'theme_font')->update([
                'value' => json_encode('modern'),
                'updated_at' => now(),
            ]);
        }
    }
};
