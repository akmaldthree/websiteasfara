<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_accounts', function (Blueprint $table) {
            $table->string('legal_document_path')->nullable()->after('notes');
            $table->string('legal_document_name')->nullable()->after('legal_document_path');
            $table->foreignId('sales_user_id')->nullable()->after('price_group_id')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('credit_terms_days')->default(0)->after('sales_user_id');
            $table->unsignedBigInteger('credit_limit')->default(0)->after('credit_terms_days');
            $table->decimal('commission_percent', 5, 2)->default(0)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('business_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_user_id');
            $table->dropColumn(['legal_document_path', 'legal_document_name', 'credit_terms_days', 'credit_limit', 'commission_percent']);
        });
    }
};
