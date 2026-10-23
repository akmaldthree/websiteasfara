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
        Schema::create('price_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('business_type', 30);
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->unsignedBigInteger('minimum_order_amount')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('business_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_type', 30);
            $table->string('business_name', 150);
            $table->string('sales_channel', 150);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('price_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minimum_quantity');
            $table->unsignedBigInteger('price');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['variant_id', 'price_group_id', 'minimum_quantity']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('price_group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('business_snapshot')->nullable()->after('price_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_group_id');
            $table->dropColumn('business_snapshot');
        });
        Schema::dropIfExists('variant_prices');
        Schema::dropIfExists('business_accounts');
        Schema::dropIfExists('price_groups');
    }
};
