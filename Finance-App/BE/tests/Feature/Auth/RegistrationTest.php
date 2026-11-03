<?php

namespace Tests\Feature\Auth;

use App\Mail\KodeVerifikasiMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('otp.show', absolute: false));

        $this->assertDatabaseHas('user', [
            'email' => 'test@example.com',
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
            'otp_verified_at' => null,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertNotNull($user->otp_kode, 'Kode OTP harus tersimpan (hashed) setelah registrasi.');
        $this->assertNotNull($user->otp_expires_at, 'Kode OTP harus memiliki masa berlaku.');

        Mail::assertSent(KodeVerifikasiMail::class, function (KodeVerifikasiMail $mail) use ($user) {
            return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $mail->kode) === 1;
        });
    }
}
