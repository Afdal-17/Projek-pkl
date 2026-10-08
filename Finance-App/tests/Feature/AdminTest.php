<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Dompet;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee($user->email);
        $this->actingAs($admin)->get(route('dompet.index'))->assertForbidden();
    }

    public function test_admin_can_disable_and_reactivate_a_user(): void
    {
        $admin = $this->user('admin-status@example.com', 'admin');
        $user = $this->user('toggle@example.com', 'user');

        $this->actingAs($admin)->patch(route('admin.users.status', $user))->assertRedirect();
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => false]);
        $this->actingAs($user->fresh())->get(route('user.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->actingAs($admin)->patch(route('admin.users.status', $user))->assertRedirect();
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => true]);
    }

    public function test_admin_can_edit_and_delete_a_user_without_transfer_history(): void
    {
        $admin = $this->user('admin-manage@example.com', 'admin');
        $user = $this->user('managed@example.com', 'user');
        $user->update(['status' => false, 'last_seen_at' => now()->subDays(31)]);

        $this->actingAs($admin)->patchJson(route('admin.users.update', $user), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ])->assertOk()->assertJsonPath('name', 'Updated User');

        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'nama' => 'Updated User']);
        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user))->assertNoContent();
        $this->assertDatabaseMissing('user', ['id_user' => $user->id_user]);
    }

    public function test_admin_cannot_delete_a_user_who_is_not_long_inactive(): void
    {
        $admin = $this->user('admin-active@example.com', 'admin');
        $user = $this->user('active@example.com', 'user');

        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user))
            ->assertStatus(409);
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user]);
    }

    public function test_admin_can_ban_and_unban_a_user(): void
    {
        $admin = $this->user('admin-ban@example.com', 'admin');
        $user = $this->user('banned@example.com', 'user');

        $this->actingAs($admin)->patchJson(route('admin.users.ban', $user))
            ->assertOk()
            ->assertJsonPath('status', 'banned');
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => false, 'banned_at' => now()->toDateTimeString()]);

        $this->actingAs($user->fresh())->get(route('user.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda telah diblokir. Silakan hubungi admin.');

        $this->actingAs($admin)->patchJson(route('admin.users.ban', $user))
            ->assertOk()
            ->assertJsonPath('status', 'active');
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user, 'status' => true]);
        $this->assertNull($user->fresh()->banned_at);
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

        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $recipient))
            ->assertStatus(409);
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

    private function user(string $email, string $role): User
    {
        return User::create([
            'nama' => $role,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'status' => true,
            'email_verified_at' => now(),
        ]);
    }
}
