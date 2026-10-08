<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_notice_page_is_shown_for_unverified_users(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertSee('verify your email');
    }

    public function test_verified_users_are_redirected_away_from_notice(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertRedirect('/');
    }

    public function test_verification_link_marks_email_as_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id_user,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect('/?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
