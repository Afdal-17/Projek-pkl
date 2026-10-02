<?php

namespace Tests\Feature;

use App\Models\User;
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
                && $summary['total_transaksi'] === 0);
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

    private function user(string $email, string $role): User
    {
        return User::create([
            'nama' => $role,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'status' => true,
        ]);
    }
}
