<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('credit_due_at')->nullable()->index();
        });
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('business_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('base_amount');
            $table->decimal('commission_percent', 5, 2);
            $table->string('reference')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('credit_due_at'));
    }
};
