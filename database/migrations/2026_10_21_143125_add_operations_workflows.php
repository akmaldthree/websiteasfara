<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->timestamp('received_at')->nullable());
        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('event_key', 100)->unique();
            $t->string('type', 50);
            $t->text('message');
            $t->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_event_id')->constrained()->restrictOnDelete();
            $t->string('channel', 20);
            $t->string('dedupe_key', 64)->unique();
            $t->text('recipient');
            $t->text('payload');
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable()->index();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('last_error')->nullable();
            $t->timestamps();
        });
        Schema::table('service_cases', function (Blueprint $t) {
            $t->uuid('request_key')->nullable()->unique();
            $t->string('reason_code', 100)->nullable();
            $t->string('evidence_path')->nullable();
            $t->json('policy_snapshot')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
        });
        Schema::create('case_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('restocked_quantity')->default(0);
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
            $t->unique(['service_case_id', 'order_item_id']);
        });
        Schema::create('case_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 40);
            $t->text('message');
            $t->timestamps();
        });
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_case_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('amount');
            $t->string('method', 80);
            $t->string('reference', 100);
            $t->text('reason');
            $t->string('proof_path');
            $t->timestamp('paid_at');
            $t->timestamps();
            $t->unique(['order_id', 'reference']);
        });
        Schema::table('addresses', fn (Blueprint $t) => $t->boolean('is_default')->default(false));
        foreach (DB::table('addresses')->selectRaw('MIN(id) AS id')->groupBy('user_id')->pluck('id') as $id) {
            DB::table('addresses')->where('id', $id)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('addresses', fn (Blueprint $t) => $t->dropColumn('is_default'));
        foreach (['refunds', 'case_events', 'case_items'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('service_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_id');
            $t->dropColumn(['request_key', 'reason_code', 'evidence_path', 'policy_snapshot', 'closed_at']);
        });
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('order_events');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('received_at'));
    }
};
