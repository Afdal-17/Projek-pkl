<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_account_frontend_uses_and_updates_authenticated_profile(): void
    {
        $user = User::factory()->create(['nama' => 'Account User']);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('Account User')
            ->assertSee($user->email);

        $this->actingAs($user)->patch(route('account.update'), [
            'name' => 'Updated Account User',
            'email' => 'updated-account@example.com',
            'phone' => '+628123456789',
            'location' => 'Bandung',
            'currency' => 'USD',
        ])->assertRedirect(route('account'));

        $this->assertDatabaseHas('user', [
            'id_user' => $user->id_user,
            'nama' => 'Updated Account User',
            'email' => 'updated-account@example.com',
            'nomor_telepon' => '+628123456789',
            'lokasi' => 'Bandung',
            'mata_uang' => 'USD',
        ]);

        $this->actingAs($user)->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('<html lang="en-GB">', false);
    }

    public function test_account_avatar_can_be_uploaded_served_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('account.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertOk();

        $firstAvatarPath = $user->fresh()->avatar_path;
        $firstAvatarUrl = $response->json('avatar_url');

        $this->assertNotNull($firstAvatarPath);
        $this->assertSame(route('avatars.show', ['filename' => basename($firstAvatarPath)], false), $firstAvatarUrl);
        Storage::disk('public')->assertExists($firstAvatarPath);
        $this->get($firstAvatarUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($user)->postJson(route('account.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertOk()
            ->assertJsonPath('avatar_url', route('avatars.show', ['filename' => basename($user->fresh()->avatar_path)], false));

        $avatarPath = $user->fresh()->avatar_path;
        $this->assertNotSame($firstAvatarPath, $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
        Storage::disk('public')->assertMissing($firstAvatarPath);
        $this->get($user->fresh()->avatar_url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('user.dashboard', absolute: false));
        $this->get(route('account'))
            ->assertOk()
            ->assertSee($user->fresh()->avatar_url);

        $this->actingAs($user)->deleteJson(route('account.avatar.destroy'))->assertNoContent();
        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_avatar_upload_reports_missing_invalid_and_oversized_files_as_json_validation_errors(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('account.avatar.update'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->postJson(route('account.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('not-an-image.jpg', 10, 'text/plain'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->postJson(route('account.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('too-large.png')->size(2049),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_avatar_serving_rejects_missing_files_and_path_traversal(): void
    {
        Storage::fake('public');

        $this->get(route('avatars.show', ['filename' => 'missing.jpg']))
            ->assertNotFound();
        $this->get('/avatars/..%2Foutside.jpg')
            ->assertNotFound();
    }

    public function test_invalid_or_missing_avatar_path_uses_the_initial_fallback(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/../private.jpg']);

        $this->assertNull($user->avatar_url);

        $user->update(['avatar_path' => 'avatars/missing.jpg']);
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Test User', $user->refresh()->nama);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
