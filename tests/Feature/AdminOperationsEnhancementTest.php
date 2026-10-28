<?php

namespace Tests\Feature;

use App\Models\BusinessAccount;
use App\Models\CommissionEntry;
use App\Models\CommissionPayout;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\StaffLoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOperationsEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_organization_policy_requires_two_factor_for_all_staff(): void
    {
        Notification::fake();
        Setting::put('staff_two_factor_required', true);
        $staff = User::factory()->create([
            'email' => 'required-2fa@example.com',
            'password' => Hash::make('TestingPassword123'),
        ]);
        $staff->forceFill(['role' => 'admin', 'two_factor_enabled' => false])->save();

        $this->post(route('login.submit'), ['login' => $staff->email, 'password' => 'TestingPassword123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        Notification::assertSentTo($staff, StaffLoginCode::class);

        $this->actingAs($staff)->post(route('admin.security.save'), [
            'current_password' => 'TestingPassword123',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->two_factor_enabled);
    }

    public function test_authorized_staff_can_apply_safe_bulk_order_action_with_per_order_results(): void
    {
        $warehouse = $this->staff('warehouse');
        $ready = $this->order(['status' => 'confirmed', 'fulfillment_status' => 'processing', 'inventory_state' => 'committed']);
        $invalid = $this->order(['status' => 'waiting', 'fulfillment_status' => 'unprocessed']);

        $this->actingAs($warehouse)->post(route('admin.orders.bulk-action'), [
            'order_ids' => [$ready->id, $invalid->id], 'action' => 'pack',
        ])->assertRedirect()->assertSessionHas('bulk_failures');

        $this->assertSame('packed', $ready->fresh()->fulfillment_status);
        $this->assertSame('unprocessed', $invalid->fresh()->fulfillment_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.bulk_action']);

        $finance = $this->staff('finance');
        $this->actingAs($finance)->post(route('admin.orders.bulk-action'), [
            'order_ids' => [$invalid->id], 'action' => 'pack',
        ])->assertForbidden();
    }

    public function test_finance_can_record_audited_commission_payout_with_private_proof(): void
    {
        Storage::fake('local');
        $finance = $this->staff('finance');
        $sales = $this->staff('cs');
        $account = BusinessAccount::factory()->create();
        $order = $this->order();
        CommissionEntry::create([
            'sales_user_id' => $sales->id, 'business_account_id' => $account->id, 'order_id' => $order->id,
            'type' => 'earned', 'amount' => 25000, 'base_amount' => 1000000, 'commission_percent' => 2.5,
            'reference' => 'order:'.$order->id.':commission',
        ]);

        $this->actingAs($finance)->post(route('admin.commission-payout.store'), [
            'sales_user_id' => $sales->id,
            'recipient_name' => 'Sales Dthree',
            'bank_name' => 'Bank Uji',
            'account_number' => '1234567890',
            'payment_reference' => 'TRX-COMMISSION-001',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            'notes' => 'Payout periode berjalan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payout = CommissionPayout::firstOrFail();
        $this->assertSame(25000, $payout->amount);
        $this->assertSame($payout->id, CommissionEntry::firstOrFail()->commission_payout_id);
        Storage::disk('local')->assertExists($payout->proof_path);
        $this->get(route('admin.commission-payout.proof', $payout))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'commission.paid', 'subject' => 'commission_payout:'.$payout->id]);

        $this->post(route('admin.commission-payout.store'), [
            'sales_user_id' => $sales->id, 'recipient_name' => 'Sales Dthree', 'bank_name' => 'Bank Uji',
            'account_number' => '1234567890', 'payment_reference' => 'TRX-COMMISSION-002',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('sales_user_id');
        $this->assertDatabaseCount('commission_payouts', 1);
    }

    public function test_monitoring_reports_scheduler_heartbeat_and_runtime_checks(): void
    {
        $admin = $this->staff('admin');
        $this->artisan('system:heartbeat')->assertSuccessful();

        $this->actingAs($admin)->get(route('admin.monitoring'))
            ->assertOk()
            ->assertSee('Monitoring sistem')
            ->assertSee('Heartbeat terakhir')
            ->assertSee('Database')
            ->assertSee('Queue');
    }

    public function test_order_filters_show_a_readable_credit_payment_label(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Termin kredit')
            ->assertDontSee('status.credit');
    }

    /** @param array<string, mixed> $overrides */
    private function order(array $overrides = []): Order
    {
        return Order::create($overrides + [
            'number' => 'ORD-'.fake()->unique()->numerify('########'),
            'checkout_key' => hash('sha256', fake()->unique()->uuid()),
            'name' => 'Pelanggan Uji', 'phone' => '081234567890', 'email' => 'customer@example.com',
            'address' => ['province' => 'Jawa Barat'], 'payment_method' => 'transfer',
            'subtotal' => 100000, 'shipping_cost' => 10000, 'total' => 110000,
            'shipping' => ['courier' => 'JNE', 'service' => 'REG'], 'expires_at' => now()->addDay(),
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
