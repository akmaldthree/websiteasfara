<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_entries', fn (Blueprint $table) => $table->string('placement', 30)->default('both')->after('type'));
    }

    public function down(): void
    {
        Schema::table('content_entries', fn (Blueprint $table) => $table->dropColumn('placement'));
    }
};
