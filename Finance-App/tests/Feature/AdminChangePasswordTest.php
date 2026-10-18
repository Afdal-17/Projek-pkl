<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(string $password = 'OldPassword123!'): User
    {
        return User::create([
            'nama' => 'Admin User',
            'email' => 'admin-test@finance.test',
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createRegularUser(string $password = 'UserPassword123!'): User
    {
        return User::create([
            'nama' => 'Regular User',
            'email' => 'regular-user@finance.test',
            'password' => Hash::make($password),
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_1_password_saat_ini_benar_dan_password_baru_valid_berhasil(): void
    {
        $admin = $this->createAdmin('CurrentAdminPass123!');

        $response = $this->actingAs($admin)->putJson(route('admin.password.update'), [
            'current_password' => 'CurrentAdminPass123!',
            'password' => 'BrandNewAdminPass123!',
            'password_confirmation' => 'BrandNewAdminPass123!',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'password-updated',
        ]);

        $admin->refresh();
        $this->assertTrue(Hash::check('BrandNewAdminPass123!', $admin->password));
        $this->assertFalse(Hash::check('CurrentAdminPass123!', $admin->password));
        $this->assertSame('Admin User', $admin->nama);
        $this->assertSame('admin-test@finance.test', $admin->email);
    }

    public function test_2_password_saat_ini_salah_gagal_dan_password_lama_tetap_berlaku(): void
    {
        $admin = $this->createAdmin('CurrentAdminPass123!');

        $response = $this->actingAs($admin)->putJson(route('admin.password.update'), [
            'current_password' => 'SalahPassword123!',
            'password' => 'BrandNewAdminPass123!',
            'password_confirmation' => 'BrandNewAdminPass123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);

        $admin->refresh();
        $this->assertTrue(Hash::check('CurrentAdminPass123!', $admin->password));
        $this->assertFalse(Hash::check('BrandNewAdminPass123!', $admin->password));
    }

    public function test_3_konfirmasi_password_tidak_cocok_gagal(): void
    {
        $admin = $this->createAdmin('CurrentAdminPass123!');

        $response = $this->actingAs($admin)->putJson(route('admin.password.update'), [
            'current_password' => 'CurrentAdminPass123!',
            'password' => 'BrandNewAdminPass123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);

        $admin->refresh();
        $this->assertTrue(Hash::check('CurrentAdminPass123!', $admin->password));
    }

    public function test_4_pengguna_belum_login_ditolak(): void
    {
        $response = $this->putJson(route('admin.password.update'), [
            'current_password' => 'SomePass123!',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertStatus(401);
    }

    public function test_5_pengguna_non_admin_tidak_dapat_mengakses_fitur_khusus_admin(): void
    {
        $user = $this->createRegularUser('UserPass123!');

        $response = $this->actingAs($user)->putJson(route('admin.password.update'), [
            'current_password' => 'UserPass123!',
            'password' => 'NewUserPass123!',
            'password_confirmation' => 'NewUserPass123!',
        ]);

        $response->assertStatus(403);

        $user->refresh();
        $this->assertTrue(Hash::check('UserPass123!', $user->password));
    }

    public function test_6_setelah_password_berhasil_diubah_password_baru_dapat_digunakan_login(): void
    {
        $admin = $this->createAdmin('InitialPass123!');

        $this->actingAs($admin)->putJson(route('admin.password.update'), [
            'current_password' => 'InitialPass123!',
            'password' => 'UpdatedSecret123!',
            'password_confirmation' => 'UpdatedSecret123!',
        ])->assertOk();

        // Logout
        auth()->logout();

        // Login dengan password lama gagal
        $loginFailed = $this->post(route('login'), [
            'email' => 'admin-test@finance.test',
            'password' => 'InitialPass123!',
        ]);
        $this->assertGuest();

        // Login dengan password baru berhasil
        $loginSuccess = $this->post(route('login'), [
            'email' => 'admin-test@finance.test',
            'password' => 'UpdatedSecret123!',
        ]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_profile_view_renders_change_password_section(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.profile'));

        $response->assertOk();
        $response->assertSee('Security / Change Password');
        $response->assertSee('Current Password');
        $response->assertSee('New Password');
        $response->assertSee('Confirm New Password');
        $response->assertSee('Update Password');
    }
}
