<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_quotes', fn (Blueprint $table) => $table->string('reference_path')->nullable()->after('measurements'));
    }

    public function down(): void
    {
        Schema::table('custom_quotes', fn (Blueprint $table) => $table->dropColumn('reference_path'));
    }
};
