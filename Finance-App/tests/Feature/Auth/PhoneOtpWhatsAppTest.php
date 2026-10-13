<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\FonnteService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PhoneOtpWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function aktifkanFonnte(): void
    {
        config(['services.fonnte.token' => 'token-tes-fonnte']);
    }

    private function buatUserTelepon(array $overrides = []): User
    {
        return User::create(array_merge([
            'nama' => 'Sari Wulandari',
            'nomor_telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ], $overrides));
    }

    public function test_phone_registration_sends_otp_via_fonnte_whatsapp(): void
    {
        $this->aktifkanFonnte();

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
            ]),
        ]);

        $response = $this->post(route('register'), [
            'name' => 'Rina Marlina',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('otp.show'));
        $response->assertSessionHas('otp_user_id');
        $this->assertGuest();

        $user = User::where('nomor_telepon', '081234567890')->firstOrFail();
        $this->assertFalse((bool) $user->status);
        $this->assertNotNull($user->otp_kode);
        $this->assertNull($user->otp_verified_at);

        // Pastikan Fonnte dipanggil dengan benar: token di header (tanpa Bearer),
        // nomor dinormalisasi ke format internasional, pesan berisi kode verifikasi.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.fonnte.com/send')
                && $request->header('Authorization')[0] === 'token-tes-fonnte'
                && $request['target'] === '6281234567890'
                && str_contains($request['message'], 'Kode Verifikasi')
                && str_contains($request['message'], 'Berlaku selama 10 menit');
        });
    }

    public function test_correct_whatsapp_otp_activates_account_and_logs_in(): void
    {
        $user = $this->buatUserTelepon();
        $kode = app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => $kode]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->otp_verified_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->status);
        $this->assertNull($user->otp_kode);
    }

    public function test_login_with_pending_phone_otp_is_redirected_to_otp_page(): void
    {
        $user = $this->buatUserTelepon();
        app(OtpService::class)->buatKode($user);

        $response = $this->post(route('login'), [
            'phone' => '081234567890',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('otp.show'));
        $response->assertSessionHas('otp_user_id', $user->id_user);
        $this->assertGuest();
    }

    public function test_otp_page_shows_masked_phone_for_whatsapp_channel(): void
    {
        $user = $this->buatUserTelepon();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->get(route('otp.show'));

        $expected = '0812' . str_repeat('*', strlen('081234567890') - 7) . '890';

        $response->assertOk();
        $response->assertSee($expected, false);
        $response->assertSee('(WhatsApp)', false);

        // UI menyesuaikan kanal: judul, instruksi, dan tombol memakai WhatsApp
        $response->assertSee('Verify your WhatsApp');
        $response->assertSee('verify your WhatsApp number');
        $response->assertSee('Wrong phone number?');
        $response->assertDontSee('Verify your email');
    }

    public function test_resend_uses_whatsapp_for_phone_only_user(): void
    {
        $this->aktifkanFonnte();

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);

        $user = $this->buatUserTelepon();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.resend'));

        $response->assertSessionHas('status');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.fonnte.com/send')
            && $request['target'] === '6281234567890');
    }

    public function test_fonnte_failure_falls_back_to_admin_approval(): void
    {
        $this->aktifkanFonnte();

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => false,
                'reason' => 'target invalid',
            ]),
        ]);

        $response = $this->post(route('register'), [
            'name' => 'Budi',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Fallback: kembali ke alur verifikasi admin
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user = User::where('nomor_telepon', '081234567890')->firstOrFail();
        $this->assertFalse((bool) $user->status);
        // Kode OTP dibersihkan agar login-gate tidak mengblokir alur fallback admin
        $this->assertNull($user->otp_kode);
    }

    public function test_phone_normalization_for_fonnte(): void
    {
        $fonnte = app(FonnteService::class);

        $this->assertSame('6281234567890', $fonnte->normalisasi('081234567890'));
        $this->assertSame('6281234567890', $fonnte->normalisasi('+6281234567890'));
        $this->assertSame('6281234567890', $fonnte->normalisasi('6281234567890'));
        $this->assertSame('6281234567890', $fonnte->normalisasi('0812-3456-7890'));
    }

    public function test_fonnte_inactive_without_token(): void
    {
        config(['services.fonnte.token' => null]);

        $this->assertFalse(app(FonnteService::class)->aktif());

        $this->expectException(RuntimeException::class);

        app(FonnteService::class)->kirim('081234567890', 'tes');
    }
}
