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
        Schema::create('measurement_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('product_type', 100)->nullable();
            $table->string('pricing_mode', 20)->default('fixed');
            $table->unsignedBigInteger('surcharge')->default(0);
            $table->string('lead_time', 150);
            $table->json('fields');
            $table->text('policy')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('variants', function (Blueprint $table) {
            $table->foreignId('measurement_template_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('custom_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('measurement_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('template_snapshot');
            $table->json('measurements');
            $table->string('status', 30)->default('submitted')->index();
            $table->unsignedBigInteger('surcharge')->nullable();
            $table->string('lead_time', 150)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('orders', fn (Blueprint $table) => $table->unsignedBigInteger('custom_cost')->default(0)->after('subtotal'));
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_cost')->default(0)->after('price');
            $table->string('production_status', 30)->nullable()->index();
        });
        Schema::create('production_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_milestones');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['custom_cost', 'production_status']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('custom_cost'));
        Schema::dropIfExists('custom_quotes');
        Schema::table('variants', fn (Blueprint $table) => $table->dropConstrainedForeignId('measurement_template_id'));
        Schema::dropIfExists('measurement_templates');
    }
};
