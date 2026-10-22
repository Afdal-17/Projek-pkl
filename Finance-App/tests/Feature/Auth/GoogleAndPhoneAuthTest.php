<?php

namespace Tests\Feature\Auth;

use App\Mail\KodeVerifikasiMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAndPhoneAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_shows_google_and_phone_options(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee(route('auth.google', ['mode' => 'login']));
        $response->assertSee('Continue with Google');
        $response->assertSee('Continue with Phone');
    }

    public function test_register_screen_shows_google_and_phone_options(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSee(route('auth.google', ['mode' => 'register']));
        $response->assertSee('Sign up with Google');
        $response->assertSee('Continue with Phone');
    }

    public function test_user_can_register_with_phone_number(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('user', [
            'nama' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'role' => 'user',
            'status' => false,
        ]);
    }

    public function test_user_can_authenticate_with_phone_number(): void
    {
        $user = User::create([
            'nama' => 'Siti Rahma',
            'nomor_telepon' => '089876543210',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'phone' => '089876543210',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('user.dashboard'));
    }

    public function test_user_can_authenticate_with_phone_number_in_email_field(): void
    {
        $user = User::create([
            'nama' => 'Ahmad Fauzi',
            'nomor_telepon' => '085512345678',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email' => '085512345678',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('user.dashboard'));
    }

    public function test_google_redirect_returns_error_if_not_configured(): void
    {
        config(['services.google.client_id' => '']);
        config(['services.google.client_secret' => '']);

        $response = $this->get(route('auth.google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_google_login_rejected_if_user_not_registered(): void
    {
        config([
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-unregistered');
        $abstractUser->shouldReceive('getName')->andReturn('Unknown Google User');
        $abstractUser->shouldReceive('getNickname')->andReturn('unknown');
        $abstractUser->shouldReceive('getEmail')->andReturn('unknown@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // Simulasi user klik login dari halaman login (mode = login)
        $response = $this->withSession(['google_auth_mode' => 'login'])
            ->get(route('auth.google.callback'));

        // Tidak boleh langsung masuk! Harus diarahkan ke register
        $this->assertGuest();
        $response->assertRedirect(route('register'));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('user', [
            'email' => 'unknown@gmail.com',
        ]);
    }

    public function test_user_can_register_with_google_callback(): void
    {
        Mail::fake();

        config([
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-new-user');
        $abstractUser->shouldReceive('getName')->andReturn('New Google User');
        $abstractUser->shouldReceive('getNickname')->andReturn('newuser');
        $abstractUser->shouldReceive('getEmail')->andReturn('newuser@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // User mendaftar dari halaman register (mode = register)
        $response = $this->withSession(['google_auth_mode' => 'register'])
            ->get(route('auth.google.callback'));

        // Register via Google: kode OTP dikirim, user dibawa ke halaman OTP (belum login)
        $this->assertGuest();
        $response->assertRedirect(route('otp.show'));

        $user = User::where('email', 'newuser@gmail.com')->firstOrFail();
        $this->assertSame('google-new-user', $user->google_id);
        $this->assertSame('user', $user->role);
        $this->assertFalse((bool) $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->otp_verified_at);
        $this->assertNotNull($user->otp_kode);
        $this->assertSame($user->id_user, session('otp_user_id'));

        Mail::assertSent(KodeVerifikasiMail::class, function (KodeVerifikasiMail $mail) {
            return $mail->hasTo('newuser@gmail.com');
        });

        // Kode OTP benar → langsung masuk dashboard
        $kode = app(OtpService::class)->buatKode($user);

        $verify = $this->withSession(['otp_user_id' => $user->id_user])
            ->post(route('otp.verify'), ['kode' => $kode]);

        $verify->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue((bool) $user->fresh()->status);
    }

    public function test_existing_user_can_login_with_google_callback(): void
    {
        config([
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
        ]);

        $existingUser = User::create([
            'nama' => 'Existing Google Member',
            'email' => 'existing@gmail.com',
            'google_id' => 'google-existing-99',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-existing-99');
        $abstractUser->shouldReceive('getName')->andReturn('Existing Google Member');
        $abstractUser->shouldReceive('getNickname')->andReturn('existing');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // User login dari halaman login
        $response = $this->withSession(['google_auth_mode' => 'login'])
            ->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect(route('user.dashboard'));
    }

    public function test_banned_user_cannot_login_with_google(): void
    {
        config([
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
        ]);

        $user = User::create([
            'nama' => 'Banned Google Member',
            'email' => 'banned-google@gmail.com',
            'google_id' => 'google-banned-99',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => now(),
            'banned_at' => now(),
            'ban_reason' => 'Pelanggaran',
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-banned-99');
        $abstractUser->shouldReceive('getName')->andReturn('Banned Google Member');
        $abstractUser->shouldReceive('getNickname')->andReturn('banned');
        $abstractUser->shouldReceive('getEmail')->andReturn('banned-google@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withSession(['google_auth_mode' => 'login'])
            ->get(route('auth.google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('banned_modal');
        $this->assertSame('google-banned-99', $user->fresh()->google_id);
        $this->assertFalse((bool) $user->fresh()->status);
    }

    public function test_inactive_verified_user_cannot_login_with_google(): void
    {
        config([
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
        ]);

        $user = User::create([
            'nama' => 'Inactive Google Member',
            'email' => 'inactive-google@gmail.com',
            'google_id' => 'google-inactive-99',
            'password' => Hash::make('secret123'),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ]);
        $user->forceFill(['otp_verified_at' => now()])->save();

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-inactive-99');
        $abstractUser->shouldReceive('getName')->andReturn('Inactive Google Member');
        $abstractUser->shouldReceive('getNickname')->andReturn('inactive');
        $abstractUser->shouldReceive('getEmail')->andReturn('inactive-google@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withSession(['google_auth_mode' => 'login'])
            ->get(route('auth.google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse((bool) $user->fresh()->status);
    }
}
