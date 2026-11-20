<?php

namespace Tests\Feature\Auth;

use App\Mail\KodeVerifikasiMail;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GoogleMobileAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'finance-web-client.test';

    private const KEY_ID = 'google-test-key';

    private const PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----\n"
        ."MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCddD4xpy/ooKgx\n"
        ."YtLvFjsYGGvXNGaej7tqlPK7cx79PGY61/2LDTZBi6tVbT1O8sVS/MIOiD5RZQlW\n"
        ."g+vp1uW2B9l782P6R0YmZAYywM0aOZAk7WGid6gP2DG0/gc1Ot2y4/+EAfNjBQVo\n"
        ."W8IMRiSMslS7zjs/VMk91mhm0QWSQwnQwU6lwqfDERanHvuetvbkNdMgDdr1PNZO\n"
        ."7k7KOu2WjAdjQOXzYsVJu4dvvciMs+6rcCGblWYstIUQAsrWVkEtRDXG4g05rOaD\n"
        ."1iW6jTJMZGdOwyhKlAEr2UiaM0IxJb3lksuA8MiXpMzY5rPx9YWJ3vhnZxac/4Yf\n"
        ."4W194ysLAgMBAAECggEAE6WJCyYK3tKJGhx0KHwUJJYLgr9ZnKhfoNV0Ejqzn7KV\n"
        ."KPuBegPMhNI6Lz8husOlJo0+Y5TRfdOHHkH4wmVbpWKGWyaMD5MsKCcr9NJE5WRQ\n"
        ."XaM3HxAiKtP1o9lcqeIt/p/fZqfdqDncqmBkrqZj8iBgXUzG7MiWEb66qTWkpdLA\n"
        ."fNrlBNOO50HRI8sG9mAuLXY/s4Z6khl/VGvULmq6C+Zz+gaERTaJDWzjH74PhldB\n"
        ."PvtNTDhZb/dSwTsVfZX1CwdovSxF/eD6MS97AFHssBI91FgshVKMQ/ryKjzsJQqZ\n"
        ."ltaSx5cqnJTjQ7BjqYMZKi5pWV850kVfAjvKD5ESmQKBgQDN0HmOip6rh4AfQAsx\n"
        ."9hi5xG2vXrUPaRPrskjD6UjUWYCOrc06Id92bsn4HzqbQ5ZLzNiPYRPpd5OZhugE\n"
        ."lb1pG+MC8lEheCtqJuuTAz+e/fA2+PwlQJPB5xqW5An0dS/6f46eQla7PL+k2zzK\n"
        ."i1saarpMDbpD3ib1PkgVJawKMwKBgQDD2PriSNwV0KLASTjscSf3GuqGB6858Y5R\n"
        ."H7+t5tmCY6z64JtHZiw5RXpQ4zApiDP8TTlRSObVXse1WB3d4fJfmufluPhRSku3\n"
        ."bmEa7p88PwnE6Glttl3M9Li92jDuIO2OoppUVwGKoa074hAs7f2ur4V7+XyjXWbf\n"
        ."IDdVlB0zyQKBgQCfzP+kykKagjHbkblfiGn4SuizIoDIv1iFR78xxf8TSY8EuTPA\n"
        ."sC42YZ0VtwGlLhwTLLD02nZAbuS+br3VWYJgixN+5TBTIV/sIt4EHqCWTSrnM/lq\n"
        ."3zIaetrtT4/fK+IdyE6u2GlJJcjTMpqH1R3ebAHMLVhze5iaEEqdiMuEewKBgQCB\n"
        ."KBMsMAiUimcKkD9uDqHgoGuRwHeaqubEMT/jm55OExX8zSsaRxNbIaqstWcJvL0r\n"
        ."4nGuXXl9tWfW/CmWLm/9BNdx9C4uzeRpL+3yKPPyc1Ap/2un4uCxTaKX8dDa+fQw\n"
        ."YKOOg2KM3lq0Zz1ro/fsv+wz+jBsPCifdUuIsIkhoQKBgBdZSMLxmnKqIeqYil8d\n"
        ."qucKUJL0ji1GIlItBA+iyN8PcEcWAHyqLzBaURYAjLmMouNU+1YmXm5kyP5WmC6Z\n"
        ."0+meUsSPj4fajGpnbxfRvxCr5h0JX8lusTy4XSIb+wp2s/uZ7OMXAVphdZ25skML\n"
        ."NXvRhRIkvhnB1uFKzokGNlU4\n"
        ."-----END PRIVATE KEY-----\n";

    /**
     * @var array<string, mixed>
     */
    private const JWKS = [
        'keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::KEY_ID,
            'n' => 'nXQ-Macv6KCoMWLS7xY7GBhr1zRmno-7apTyu3Me_TxmOtf9iw02QYurVW09TvLFUvzCDog-UWUJVoPr6dbltgfZe_Nj-kdGJmQGMsDNGjmQJO1honeoD9gxtP4HNTrdsuP_hAHzYwUFaFvCDEYkjLJUu847P1TJPdZoZtEFkkMJ0MFOpcKnwxEWpx77nrb25DXTIA3a9TzWTu5OyjrtlowHY0Dl82LFSbuHb73IjLPuq3Ahm5VmLLSFEALK1lZBLUQ1xuINOazmg9Yluo0yTGRnTsMoSpQBK9lImjNCMSW95ZLLgPDIl6TM2Oaz8fWFid74Z2cWnP-GH-FtfeMrCw',
            'e' => 'AQAB',
        ]],
    ];

    private GoogleIdTokenVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => self::CLIENT_ID]);

        $this->verifier = new GoogleIdTokenVerifier;
        $this->verifier->setJwks(self::JWKS);
        $this->app->instance(GoogleIdTokenVerifier::class, $this->verifier);
    }

    public function test_valid_id_token_authenticates_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.test',
            'google_id' => 'google-sub-123',
            'status' => true,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(),
            'mode' => 'login',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id_user', $user->id_user)
            ->assertJsonPath('user.email', 'mobile@example.test')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.otp_attempts')
            ->assertJsonStructure(['message', 'token', 'user']);
    }

    public function test_invalid_audience_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(['aud' => 'another-client.test']),
            'mode' => 'login',
        ]);

        $response->assertUnauthorized();
    }

    public function test_expired_id_token_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken([
                'iat' => time() - 7200,
                'exp' => time() - 3600,
            ]),
            'mode' => 'login',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(['email_verified' => false]),
            'mode' => 'login',
        ]);

        $response->assertUnauthorized();
    }

    public function test_bad_signature_is_rejected_without_refetching_known_key(): void
    {
        Cache::flush();
        Http::fake([
            GoogleIdTokenVerifier::GOOGLE_CERTS_URL => Http::response(self::JWKS),
        ]);

        $verifier = new GoogleIdTokenVerifier;
        $token = $this->tamperSignature($this->makeToken());

        try {
            $verifier->verify($token);
            $this->fail('A token with an invalid signature must be rejected.');
        } catch (SignatureInvalidException) {
            Http::assertSentCount(1);
        }
    }

    public function test_unknown_key_refreshes_jwks_once(): void
    {
        Cache::flush();

        $oldJwks = self::JWKS;
        $oldJwks['keys'][0]['kid'] = 'old-google-test-key';
        $requestCount = 0;
        Http::fake(function () use (&$requestCount, $oldJwks) {
            $requestCount++;

            return Http::response($requestCount === 1 ? $oldJwks : self::JWKS);
        });

        $verifier = new GoogleIdTokenVerifier;
        $verifier->verify($this->makeToken());

        $this->assertSame(2, $requestCount);
    }

    public function test_spoofed_profile_fields_are_ignored(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.test',
            'google_id' => 'google-sub-123',
            'status' => true,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
        ]);

        $victim = User::factory()->create([
            'email' => 'other@example.test',
            'google_id' => 'google-sub-other',
            'status' => true,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(),
            'mode' => 'login',
            'email' => $victim->email,
            'google_id' => $victim->google_id,
            'nama' => 'Untrusted Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id_user', $user->id_user)
            ->assertJsonPath('user.email', 'mobile@example.test');
    }

    public function test_existing_email_linked_to_another_google_account_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'mobile@example.test',
            'google_id' => 'previous-google-sub',
            'status' => true,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(),
            'mode' => 'login',
        ]);

        $response->assertStatus(409);
    }

    public function test_register_creates_pending_user_and_sends_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken([
                'email' => 'new-user@example.test',
                'sub' => 'new-google-sub',
                'name' => 'New Google User',
            ]),
            'mode' => 'register',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'user_id', 'email'])
            ->assertJsonMissingPath('token');

        $user = User::where('email', 'new-user@example.test')->firstOrFail();
        $this->assertSame('new-google-sub', $user->google_id);
        $this->assertFalse((bool) $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->otp_verified_at);
        $this->assertNotNull($user->otp_kode);

        Mail::assertSent(KodeVerifikasiMail::class, function (KodeVerifikasiMail $mail): bool {
            return $mail->hasTo('new-user@example.test');
        });
    }

    public function test_expired_ban_is_cleared_and_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.test',
            'google_id' => 'google-sub-123',
            'status' => false,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
            'banned_at' => now()->subDays(31),
            'ban_reason' => 'Temporary suspension',
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(),
            'mode' => 'login',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $user->fresh()->status);
        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_missing_id_token_returns_validation_error(): void
    {
        $this->postJson('/api/auth/google', ['mode' => 'login'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_token');
    }

    public function test_google_login_route_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'mobile@example.test',
            'google_id' => 'google-sub-123',
            'status' => true,
            'email_verified_at' => now(),
            'otp_verified_at' => now(),
        ]);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/google', [
                'id_token' => $this->makeToken(),
                'mode' => 'login',
            ])->assertOk();
        }

        $this->postJson('/api/auth/google', [
            'id_token' => $this->makeToken(),
            'mode' => 'login',
        ])->assertTooManyRequests();
    }

    public function test_database_rejects_duplicate_google_ids(): void
    {
        User::factory()->create([
            'email' => 'first@example.test',
            'google_id' => 'duplicate-google-sub',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'email' => 'second@example.test',
            'google_id' => 'duplicate-google-sub',
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function makeToken(array $claims = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'google-sub-123',
            'email' => 'mobile@example.test',
            'email_verified' => true,
            'name' => 'Mobile Google User',
            'picture' => 'https://example.test/avatar.png',
            'iat' => time() - 10,
            'exp' => time() + 3600,
        ], $claims), self::PRIVATE_KEY, 'RS256', self::KEY_ID);
    }

    /**
     * @return non-empty-string
     */
    private function tamperSignature(string $token): string
    {
        [$header, $payload, $signature] = explode('.', $token);
        $signature[0] = $signature[0] === 'A' ? 'B' : 'A';

        return $header.'.'.$payload.'.'.$signature;
    }
}
