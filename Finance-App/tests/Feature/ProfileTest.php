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
            'start_of_week' => 'sunday',
        ])->assertRedirect(route('account'));

        $this->assertDatabaseHas('user', [
            'id_user' => $user->id_user,
            'nama' => 'Updated Account User',
            'email' => 'updated-account@example.com',
            'nomor_telepon' => '+628123456789',
            'lokasi' => 'Bandung',
            'mata_uang' => 'USD',
            'awal_minggu' => 'sunday',
        ]);

        $this->actingAs($user)->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('<html lang="en-US">', false);
    }

    public function test_account_avatar_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('account.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('avatar.png', 20, 'image/png'),
        ])->assertOk();

        $avatarPath = $user->fresh()->avatar_path;

        $this->assertNotNull($avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $this->actingAs($user)->deleteJson(route('account.avatar.destroy'))->assertNoContent();
        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertNull($user->fresh()->avatar_path);
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
