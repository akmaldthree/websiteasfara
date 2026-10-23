<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->string('template', 30)->default('standard')->after('body'));
        Schema::table('content_entries', fn (Blueprint $table) => $table->string('template', 30)->default('standard')->after('body'));
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('template'));
        Schema::table('content_entries', fn (Blueprint $table) => $table->dropColumn('template'));
    }
};
