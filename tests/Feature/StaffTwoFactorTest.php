<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffLoginCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_staff_with_two_factor_must_verify_emailed_code_before_dashboard_access(): void
    {
        Notification::fake();
        $staff = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('TestingPassword123'),
        ]);
        $staff->forceFill(['role' => 'admin', 'two_factor_enabled' => true])->save();

        $this->post(route('login.submit'), ['login' => $staff->email, 'password' => 'TestingPassword123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $code = null;
        Notification::assertSentTo($staff, StaffLoginCode::class, function (StaffLoginCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_staff_can_enable_two_factor_only_with_current_password(): void
    {
        $staff = User::factory()->create(['password' => Hash::make('TestingPassword123')]);
        $staff->forceFill(['role' => 'admin'])->save();

        $this->actingAs($staff)->post(route('admin.security.save'), [
            'current_password' => 'salah', 'two_factor_enabled' => '1',
        ])->assertSessionHasErrors('current_password');
        $this->assertFalse($staff->fresh()->two_factor_enabled);

        $this->actingAs($staff)->post(route('admin.security.save'), [
            'current_password' => 'TestingPassword123', 'two_factor_enabled' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->two_factor_enabled);
        $this->assertNotNull($staff->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.two_factor_updated', 'subject' => 'user:'.$staff->id]);
    }
}
