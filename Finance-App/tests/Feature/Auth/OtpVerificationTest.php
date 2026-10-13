<?php

namespace Tests\Feature\Auth;

use App\Mail\KodeVerifikasiMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function userPendingOtp(array $attributes = []): User
    {
        $user = User::factory()->unverified()->create($attributes);

        $user->forceFill([
            'otp_verified_at' => null,
            'otp_kode' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();

        return $user;
    }

    public function test_otp_page_shows_masked_email_after_registration(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response = $this->get(route('otp.show'));

        $response->assertOk();
        $response->assertSee('Verify your email');
        $response->assertSee('te**@example.com', false);
        $response->assertDontSee('test@example.com', false);

        // Bug regresi: kutip ganda escaped (\") di dalam atribut HTML akan memutus atribut
        // x-data dan membuat kode JS tumpah sebagai teks halaman.
        $response->assertDontSee('\\"', false);

        // Halaman OTP kini memakai JS murni (tanpa Alpine): kotak dirender Blade,
        // fokus otomatis pindah ke kolom kosong lewat fungsi fokusKosong().
        $response->assertSee('otp-box', false);
        $response->assertSee('fokusKosong()', false);
        $response->assertSee('id="otp-kode"', false);
    }

    public function test_otp_page_requires_pending_registration_session(): void
    {
        $response = $this->get(route('otp.show'));

        $response->assertRedirect(route('login'));
    }

    public function test_correct_otp_verifies_email_and_logs_user_into_dashboard(): void
    {
        $user = $this->userPendingOtp();
        $kode = app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => $kode]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        $user->refresh();
        $this->assertNotNull($user->otp_verified_at);
        $this->assertNull($user->otp_kode);
        $this->assertNotNull($user->email_verified_at, 'Kode benar = email terverifikasi otomatis.');
        $this->assertTrue((bool) $user->status, 'Akun langsung aktif setelah kode benar.');
        $this->assertNull(session('otp_user_id'));
    }

    public function test_wrong_otp_increments_attempts_and_reports_remaining(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => '000000']);

        $response->assertSessionHasErrors('kode');
        $this->assertSame(1, $user->fresh()->otp_attempts);
        $this->assertNull($user->fresh()->otp_verified_at);
    }

    public function test_invalid_otp_format_is_rejected(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => 'abc12']);

        $response->assertSessionHasErrors('kode');
        $this->assertSame(0, $user->fresh()->otp_attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);
        $user->forceFill(['otp_expires_at' => now()->subMinute()])->save();

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => '123456']);

        $response->assertSessionHasErrors('kode');
        $this->assertStringContainsString('kedaluwarsa', session('errors')->first('kode'));
        $this->assertNull($user->fresh()->otp_verified_at);
    }

    public function test_too_many_attempts_requires_new_code(): void
    {
        $user = $this->userPendingOtp();
        $kode = app(OtpService::class)->buatKode($user);
        $user->forceFill(['otp_attempts' => OtpService::MAKS_PERCOBAAN])->save();

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => $kode]);

        $response->assertSessionHasErrors('kode');
        $this->assertStringContainsString('minta kode OTP baru', session('errors')->first('kode'));
        $this->assertNull($user->fresh()->otp_verified_at);
    }

    public function test_resend_sends_new_otp_code(): void
    {
        Mail::fake();

        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertSent(KodeVerifikasiMail::class, function (KodeVerifikasiMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertSame(0, $user->fresh()->otp_attempts, 'Percobaan harus direset saat kode baru dikirim.');
    }

    public function test_incomplete_otp_is_rejected(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => '12345']);

        $response->assertSessionHasErrors('kode');
        $this->assertGuest();
        $this->assertNull($user->fresh()->otp_verified_at);
    }

    public function test_after_correct_otp_dashboard_renders_successfully(): void
    {
        $user = $this->userPendingOtp();
        $kode = app(OtpService::class)->buatKode($user);

        $response = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => $kode]);

        $response->assertRedirect(route('user.dashboard'));

        // Pastikan halaman dashboard benar-benar bisa dimuat setelah verifikasi
        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('/verify-otp', false);
    }

    public function test_user_without_otp_cannot_login_and_is_sent_to_otp_page(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('otp.show'));
        $this->assertSame($user->id_user, session('otp_user_id'));
    }

    public function test_verified_otp_user_can_login_with_password_later(): void
    {
        $user = $this->userPendingOtp();
        app(OtpService::class)->buatKode($user);

        // OTP terverifikasi (langsung aktif, tanpa menunggu admin)
        $user->forceFill([
            'otp_verified_at' => now(),
            'email_verified_at' => now(),
            'status' => true,
        ])->save();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('user.dashboard'));
    }

    public function test_phone_registration_without_email_skips_otp(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        Mail::assertNothingSent();
        $this->assertNull(session('otp_user_id'));
    }
}
