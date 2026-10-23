<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('recipient_name');
            $table->string('bank_name', 100);
            $table->string('account_number', 100);
            $table->string('payment_reference', 150)->unique();
            $table->dateTime('paid_at');
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->foreignId('commission_payout_id')->nullable()->after('refund_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_payout_id');
        });
        Schema::dropIfExists('commission_payouts');
    }
};
