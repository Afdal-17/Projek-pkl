<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function aktifkanFonnte(): void
    {
        config(['services.fonnte.token' => 'token-tes-fonnte']);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
            ]),
        ]);
    }

    private function buatUserTelepon(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'nomor_telepon' => '081234567890',
            'otp_verified_at' => now(),
            'status' => true,
        ], $overrides));
    }

    private function kodeYangDikirim(): string
    {
        $pesan = null;

        Http::assertSent(function ($request) use (&$pesan) {
            if (! str_contains($request->url(), 'api.fonnte.com/send')) {
                return false;
            }

            $pesan = (string) $request['message'];

            return true;
        });

        preg_match('/(?<!\d)(\d{6})(?!\d)/', (string) $pesan, $matches);

        return $matches[1] ?? '';
    }

    public function test_verified_local_phone_account_can_reset_password_through_whatsapp_otp(): void
    {
        $this->aktifkanFonnte();
        $user = $this->buatUserTelepon();
        $oldPassword = $user->password;

        $this->postJson('/api/forgot-password/phone', [
            'phone' => '+62 812-3456-7890',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['target'] === '6281234567890'
            && str_contains($request['message'], 'Pemulihan Password'));
        $code = $this->kodeYangDikirim();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $verification = $this->postJson('/api/forgot-password/phone/verify', [
            'phone' => '6281234567890',
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['reset_token']);

        $resetToken = $verification->json('reset_token');

        $this->postJson('/api/reset-password/phone', [
            'phone' => '081234567890',
            'reset_token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertNotSame($oldPassword, $user->fresh()->password);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));

        $this->postJson('/api/reset-password/phone', [
            'phone' => '081234567890',
            'reset_token' => $resetToken,
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])->assertUnprocessable();
    }

    public function test_request_code_uses_same_response_for_unknown_google_and_unverified_phones(): void
    {
        $this->aktifkanFonnte();
        $this->buatUserTelepon([
            'nomor_telepon' => '081111111111',
            'google_id' => 'google-phone-reset',
        ]);
        $this->buatUserTelepon([
            'nomor_telepon' => '082222222222',
            'otp_verified_at' => null,
        ]);

        $googleResponse = $this->postJson('/api/forgot-password/phone', [
            'phone' => '081111111111',
        ]);
        $unverifiedResponse = $this->postJson('/api/forgot-password/phone', [
            'phone' => '082222222222',
        ]);
        $unknownResponse = $this->postJson('/api/forgot-password/phone', [
            'phone' => '083333333333',
        ]);

        $googleResponse->assertOk();
        $unverifiedResponse->assertExactJson($googleResponse->json());
        $unknownResponse->assertExactJson($googleResponse->json());
        Http::assertNothingSent();
    }

    public function test_phone_recovery_rejects_wrong_code_after_five_attempts(): void
    {
        $this->aktifkanFonnte();
        $this->buatUserTelepon();

        $this->postJson('/api/forgot-password/phone', [
            'phone' => '081234567890',
        ])->assertOk();
        $correctCode = $this->kodeYangDikirim();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password/phone/verify', [
                'phone' => '081234567890',
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/forgot-password/phone/verify', [
            'phone' => '081234567890',
            'code' => $correctCode,
        ])->assertUnprocessable();
    }

    public function test_phone_reset_code_expires_after_ten_minutes(): void
    {
        $this->aktifkanFonnte();
        $this->buatUserTelepon();

        $this->postJson('/api/forgot-password/phone', [
            'phone' => '081234567890',
        ])->assertOk();
        $code = $this->kodeYangDikirim();

        $this->travel(11)->minutes();

        $this->postJson('/api/forgot-password/phone/verify', [
            'phone' => '081234567890',
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_phone_reset_endpoints_validate_required_fields(): void
    {
        $this->postJson('/api/forgot-password/phone', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/forgot-password/phone/verify', [
            'phone' => '081234567890',
            'code' => '123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/reset-password/phone', [
            'phone' => '081234567890',
            'reset_token' => 'token',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable();
    }

    public function test_failed_whatsapp_delivery_does_not_leave_a_usable_code(): void
    {
        config(['services.fonnte.token' => 'token-tes-fonnte']);
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => false,
                'reason' => 'target invalid',
            ]),
        ]);
        $user = $this->buatUserTelepon();
        $phoneKey = hash('sha256', '6281234567890');

        $this->postJson('/api/forgot-password/phone', [
            'phone' => $user->nomor_telepon,
        ])->assertOk();

        $this->assertNull(Cache::store('array')->get('phone-password-reset:otp:'.$phoneKey));
    }

    public function test_fonnte_not_configured_does_not_create_a_recovery_code(): void
    {
        config(['services.fonnte.token' => null]);
        $user = $this->buatUserTelepon();
        $phoneKey = hash('sha256', '6281234567890');

        $this->postJson('/api/forgot-password/phone', [
            'phone' => $user->nomor_telepon,
        ])->assertOk();

        $this->assertNull(Cache::store('array')->get('phone-password-reset:otp:'.$phoneKey));
        Http::assertNothingSent();
    }

    public function test_ambiguous_normalized_phone_does_not_send_a_recovery_code(): void
    {
        $this->aktifkanFonnte();
        $this->buatUserTelepon([
            'nomor_telepon' => '081234567890',
        ]);
        $this->buatUserTelepon([
            'nomor_telepon' => '+6281234567890',
        ]);

        $this->postJson('/api/forgot-password/phone', [
            'phone' => '6281234567890',
        ])->assertOk();

        Http::assertNothingSent();
    }
}
