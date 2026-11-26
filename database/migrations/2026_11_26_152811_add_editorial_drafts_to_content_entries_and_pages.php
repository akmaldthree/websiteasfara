<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['content_entries', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('editorial_draft')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['content_entries', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('editorial_draft');
            });
        }
    }
};
