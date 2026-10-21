<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('customer')->index();
            $t->string('phone', 30)->nullable();
            $t->boolean('active')->default(true);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('series', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->text('material')->nullable();
            $t->text('care')->nullable();
            $t->text('size_guide')->nullable();
            $t->string('image')->nullable();
            $t->boolean('uses_product_type')->default(false);
            $t->boolean('active')->default(false);
            $t->boolean('featured')->default(false);
            $t->timestamps();
        });
        Schema::create('variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('series_id')->constrained('series')->restrictOnDelete();
            $t->string('sku', 80)->unique();
            $t->string('product_type', 100)->default('');
            $t->string('color', 80);
            $t->string('size', 40);
            $t->string('combination_key', 64)->unique();
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('reserved')->default(0);
            $t->unsignedInteger('low_stock')->default(5);
            $t->unsignedInteger('weight')->default(500);
            $t->string('fulfillment')->default('ready_stock');
            $t->string('lead_time')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->string('courier', 10);
            $t->string('service', 80);
            $t->string('province');
            $t->string('city');
            $t->unsignedInteger('first_kg');
            $t->unsignedInteger('extra_kg');
            $t->string('estimate');
            $t->boolean('cod')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('type');
            $t->unsignedInteger('value');
            $t->unsignedInteger('minimum')->default(0);
            $t->unsignedInteger('maximum')->nullable();
            $t->unsignedInteger('usage_limit')->default(100);
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->string('checkout_key', 64)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('email')->nullable();
            $t->json('address');
            $t->string('payment_method');
            $t->string('status')->default('waiting')->index();
            $t->string('payment_status')->default('unpaid')->index();
            $t->string('fulfillment_status')->default('unprocessed');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('shipping_cost');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('shipping_discount')->default(0);
            $t->unsignedBigInteger('cod_fee')->default(0);
            $t->unsignedBigInteger('total');
            $t->json('shipping');
            $t->json('bank_accounts')->nullable();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->string('coupon_code')->nullable();
            $t->text('notes')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->string('tracking_number')->nullable();
            $t->string('inventory_state')->default('reserved');
            $t->timestamp('expires_at')->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->string('sku');
            $t->string('name');
            $t->json('attributes');
            $t->string('fulfillment');
            $t->string('lead_time')->nullable();
            $t->unsignedInteger('weight');
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('total');
            $t->timestamps();
        });
        Schema::create('payment_proofs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('status')->default('pending');
            $t->text('reason')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('stock_delta');
            $t->integer('reserved_delta');
            $t->string('reason');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('body');
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('province');
            $t->string('city');
            $t->string('district');
            $t->string('postal_code', 5);
            $t->text('detail');
            $t->timestamps();
        });
        Schema::create('service_cases', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('type');
            $t->text('reason');
            $t->string('status')->default('open');
            $t->text('resolution')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['service_cases', 'addresses', 'pages', 'audit_logs', 'stock_movements', 'payment_proofs', 'order_items', 'orders', 'coupons', 'shipping_rates', 'variants', 'series', 'categories', 'settings', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone', 'active']));
    }
};
