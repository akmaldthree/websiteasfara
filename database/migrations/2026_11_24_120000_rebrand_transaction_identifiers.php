<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replacePrefix('orders', 'D3-', 'ASF-');
        $this->replacePrefix('orders', 'DTH-', 'ASF-');
        $this->replacePrefix('custom_quotes', 'DTH-', 'ASF-');
    }

    private function replacePrefix(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'number')) {
            return;
        }

        DB::table($table)->where('number', 'like', $from.'%')->orderBy('id')->get(['id', 'number'])->each(function ($row) use ($table, $from, $to) {
            $replacement = $to.substr($row->number, strlen($from));
            if (! DB::table($table)->where('number', $replacement)->exists()) {
                DB::table($table)->where('id', $row->id)->update(['number' => $replacement]);
            }
        });
    }

    public function down(): void
    {
        // Transaction identifiers remain branded as Asfara.
    }
};
