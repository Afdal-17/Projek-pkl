<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        Notification::assertCount(1);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_api_password_reset_returns_same_success_for_known_and_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $knownEmailResponse = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);
        $unknownEmailResponse = $this->postJson('/api/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $knownEmailResponse
            ->assertOk()
            ->assertJson(['message' => __('passwords.sent')]);
        $unknownEmailResponse
            ->assertOk()
            ->assertExactJson($knownEmailResponse->json());

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_google_account_does_not_receive_a_password_reset_link(): void
    {
        Notification::fake();

        $googleUser = User::factory()->create([
            'google_id' => 'google-reset-blocked',
        ]);
        $localUser = User::factory()->create();

        $googleResponse = $this->postJson('/api/forgot-password', [
            'email' => $googleUser->email,
        ]);
        $localResponse = $this->postJson('/api/forgot-password', [
            'email' => $localUser->email,
        ]);

        $googleResponse->assertOk()->assertExactJson($localResponse->json());
        Notification::assertSentTo($localUser, ResetPassword::class);
        Notification::assertNotSentTo($googleUser, ResetPassword::class);
    }

    public function test_google_account_cannot_reset_password_with_an_existing_token(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-reset-blocked',
            'password' => 'original-password',
        ]);
        $originalPassword = $user->password;
        $token = 'existing-reset-token';
        $emailKey = hash('sha256', strtolower($user->email));

        Cache::store('array')->put(
            'password-reset:token:'.$emailKey,
            Hash::make($token),
            now()->addMinutes(60),
        );

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_api_password_reset_still_works_for_a_local_account(): void
    {
        $user = User::factory()->create();
        $token = 'local-reset-token';
        $emailKey = hash('sha256', strtolower($user->email));

        Cache::store('array')->put(
            'password-reset:token:'.$emailKey,
            Hash::make($token),
            now()->addMinutes(60),
        );

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_google_account_cannot_reset_password_through_the_web_form(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-web-reset-blocked',
            'password' => 'original-password',
        ]);
        $originalPassword = $user->password;
        $token = 'existing-reset-token';
        $emailKey = hash('sha256', strtolower($user->email));

        Cache::store('array')->put(
            'password-reset:token:'.$emailKey,
            Hash::make($token),
            now()->addMinutes(60),
        );

        $this->from(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertRedirect(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]))
            ->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_google_account_does_not_see_the_password_reset_form(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-reset-screen-blocked',
        ]);
        $token = 'existing-reset-token';
        $emailKey = hash('sha256', strtolower($user->email));

        Cache::store('array')->put(
            'password-reset:token:'.$emailKey,
            Hash::make($token),
            now()->addMinutes(60),
        );

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_web_password_reset_does_not_send_a_link_to_google_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'google_id' => 'google-web-reset-blocked',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->get(route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
