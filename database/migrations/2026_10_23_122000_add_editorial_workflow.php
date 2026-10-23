<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'content_entries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('editorial_status', 20)->default('approved')->index();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 1000)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'content_entries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('submitted_by');
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropColumn(['editorial_status', 'submitted_at', 'reviewed_at', 'review_note']);
            });
        }
    }
};
