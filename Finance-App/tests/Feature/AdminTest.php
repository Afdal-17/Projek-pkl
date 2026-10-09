<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Dompet;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_summary_and_user_list(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $user = $this->user('user@example.com', 'user');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['total_user'] === 2
                && $summary['total_transaksi'] === 0
                && $summary['user_online'] === 1);
    }

    public function test_admin_can_disable_and_reactivate_a_user(): void
    {
        $admin = $this->user('admin-status@example.com', 'admin');
        $user = $this->user('toggle@example.com', 'user');

        // Admin tidak bisa lagi mengubah status user yang tidak dibanned
        // (fitur activate/inactive dihapus karena melanggar hak user)
        $this->actingAs($admin)->patch(route('admin.users.status', $user))->assertRedirect();
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => true]);

        // User tetap bisa mengakses dashboard karena tidak dibanned
        $this->actingAs($user->fresh())->get(route('user.dashboard'))->assertOk();
    }

    public function test_admin_can_edit_and_delete_a_user_without_transfer_history(): void
    {
        $admin = $this->user('admin-manage@example.com', 'admin');
        $user = $this->user('managed@example.com', 'user');

        // Admin tidak bisa lagi edit user (route dihapus)
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user]);
    }

    public function test_admin_cannot_delete_a_user_who_is_not_long_inactive(): void
    {
        $admin = $this->user('admin-active@example.com', 'admin');
        $user = $this->user('active@example.com', 'user');

        // Admin tidak bisa lagi delete user (route dihapus)
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user]);
    }

    public function test_admin_can_ban_and_unban_a_user(): void
    {
        $admin = $this->user('admin-ban@example.com', 'admin');
        $user = $this->user('banned@example.com', 'user');

        $this->actingAs($admin)->patchJson(route('admin.users.ban', $user), [
            'ban_reason' => 'Melanggar ketentuan penggunaan aplikasi',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'banned');
        $this->assertDatabaseHas('user', [
            'id_user' => $user->id_user,
            'status' => false,
            'ban_reason' => 'Melanggar ketentuan penggunaan aplikasi',
        ]);
        $this->assertNotNull($user->fresh()->banned_at);

        $response = $this->actingAs($user->fresh())->get(route('user.dashboard'));
        $response->assertRedirect(route('login'));
        $this->assertTrue(str_contains(session('error'), 'Akun Anda telah diblokir selama 30 hari karena Melanggar ketentuan penggunaan aplikasi'));
        $this->assertTrue(session('show_contact_admin'));

        // Test login form submission as banned user shows modal data
        Auth::logout();
        $res = $this->post(route('login'), [
            'email' => 'banned@example.com',
            'password' => 'password',
        ]);
        $res->assertRedirect(route('login'));
        $this->assertNotNull($res->getSession()->get('banned_modal'));

        $this->actingAs($admin)->patchJson(route('admin.users.ban', $user))
            ->assertOk()
            ->assertJsonPath('status', 'active');
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => true]);
        $this->assertNull($user->fresh()->banned_at);
        $this->assertNull($user->fresh()->ban_reason);
    }

    public function test_admin_cannot_delete_a_user_with_wallet_transfer_history(): void
    {
        $admin = $this->user('admin-protect@example.com', 'admin');
        $sender = $this->user('sender-protect@example.com', 'user');
        $recipient = $this->user('recipient-protect@example.com', 'user');
        $source = Dompet::create(['id_user' => $sender->id_user, 'nama_dompet' => 'Source', 'saldo_awal' => 100, 'saldo' => 100]);
        $target = Dompet::create(['id_user' => $recipient->id_user, 'nama_dompet' => 'Target', 'saldo_awal' => 0, 'saldo' => 0]);
        Transfer::create([
            'id_user' => $sender->id_user,
            'id_dompet_asal' => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah' => 10,
            'tanggal_transfer' => now(),
        ]);

        // Admin tidak bisa lagi delete user (route dihapus)
        $this->assertDatabaseHas('user', ['id_user' => $recipient->id_user]);
    }

    public function test_admin_can_verify_an_unverified_user(): void
    {
        $admin = $this->user('admin-verify@example.com', 'admin');
        $user = User::create([
            'nama' => 'Pending',
            'email' => 'pending@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ]);

        // Belum diverifikasi: user tidak bisa login
        $this->post(route('login'), [
            'email' => 'pending@example.com',
            'password' => 'password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        // Admin memverifikasi
        $this->actingAs($admin)->patchJson(route('admin.users.verify', $user))
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('user', [
            'id_user' => $user->id_user,
            'email_verified_at' => now()->toDateTimeString(),
            'status' => true,
        ]);

        // Setelah diverifikasi, user bisa login ke dashboard
        auth()->logout();

        $this->post(route('login'), [
            'email' => 'pending@example.com',
            'password' => 'password',
        ])->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_banned_user_auto_unbanned_after_30_days(): void
    {
        $admin = $this->user('admin-autoban@example.com', 'admin');
        $user = $this->user('autoban@example.com', 'user');

        // Ban user
        $this->actingAs($admin)->patchJson(route('admin.users.ban', $user), [
            'ban_reason' => 'Testing auto-unban',
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->banned_at);

        // Simulate 31 days later
        $user->update(['banned_at' => now()->subDays(31)]);

        // User tries to access - should be auto-unbanned
        $this->actingAs($user->fresh())->get(route('user.dashboard'))
            ->assertOk();

        // Verify user is unbanned
        $user->refresh();
        $this->assertNull($user->banned_at);
        $this->assertNull($user->ban_reason);
        $this->assertTrue($user->status);
    }

    public function test_user_auto_inactive_after_1_year_no_login(): void
    {
        $admin = $this->user('admin-autoinactive@example.com', 'admin');
        $user = $this->user('autoinactive@example.com', 'user');

        // Set last_seen_at to more than 1 year ago
        $user->update(['last_seen_at' => now()->subDays(366)]);

        // User tries to access dashboard - should be auto-inactive and redirected to login
        $this->actingAs($user->fresh())->get(route('user.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda tidak aktif.');

        // Verify user is now inactive
        $user->refresh();
        $this->assertFalse($user->status);
    }

    public function test_user_cannot_be_deleted_before_1_year_inactive(): void
    {
        $admin = $this->user('admin-delete@example.com', 'admin');
        $user = $this->user('delete@example.com', 'user');

        // Set last_seen_at to less than 1 year ago
        $user->update(['last_seen_at' => now()->subDays(364)]);

        // Admin tries to delete user - should fail
        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user))
            ->assertStatus(409);

        // User should still exist
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user]);
    }

    public function test_user_can_be_deleted_after_1_year_inactive(): void
    {
        $admin = $this->user('admin-delete2@example.com', 'admin');
        $user = $this->user('delete2@example.com', 'user');

        // Set last_seen_at to more than 1 year ago
        $user->update(['last_seen_at' => now()->subDays(366)]);

        // Admin tries to delete user - should succeed
        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user))
            ->assertNoContent();

        // User should be deleted
        $this->assertDatabaseMissing('user', ['id_user' => $user->id_user]);
    }

    private function user(string $email, string $role): User
    {
        return User::create([
            'nama' => $role,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => true,
            'email_verified_at' => now(),
        ]);
    }
}
