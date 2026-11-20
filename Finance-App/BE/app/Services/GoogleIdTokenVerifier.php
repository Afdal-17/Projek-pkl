<?php

namespace App\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class GoogleIdTokenVerifier
{
    public const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const JWKS_CACHE_KEY = 'google_oauth2_jwks';

    private const ALLOWED_ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $customJwks = null;

    /**
     * @param  array<string, mixed>|null  $jwks
     */
    public function setJwks(?array $jwks): self
    {
        $this->customJwks = $jwks;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJwks(): array
    {
        if ($this->customJwks !== null) {
            return $this->customJwks;
        }

        return Cache::remember(self::JWKS_CACHE_KEY, 86400, function (): array {
            try {
                $response = Http::connectTimeout(5)
                    ->timeout(10)
                    ->get(self::GOOGLE_CERTS_URL);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Google signing keys are unavailable.', 0, $e);
            }

            $jwks = $response->json();
            if (! $response->successful() || ! is_array($jwks) || empty($jwks['keys'])) {
                throw new RuntimeException('Google signing keys are unavailable.');
            }

            return $jwks;
        });
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ExpiredException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws SignatureInvalidException
     * @throws UnexpectedValueException
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        $clientId = config('services.google.client_id');

        if ($clientId === null || trim((string) $clientId) === '') {
            throw new RuntimeException('Google Web Client ID is not configured.');
        }

        $header = $this->readHeader($idToken);
        if (($header->alg ?? null) !== 'RS256') {
            throw new InvalidArgumentException('Unsupported Google ID token algorithm.');
        }

        $kid = $header->kid ?? null;
        if (! is_string($kid) || $kid === '') {
            throw new InvalidArgumentException('Google ID token has no signing key identifier.');
        }

        $keys = $this->parseKeySet($this->getJwks());
        if (! array_key_exists($kid, $keys) && $this->customJwks === null) {
            Cache::forget(self::JWKS_CACHE_KEY);
            $keys = $this->parseKeySet($this->getJwks());
        }

        if (! array_key_exists($kid, $keys)) {
            throw new InvalidArgumentException('Google ID token signing key is unknown.');
        }

        $payload = (array) JWT::decode($idToken, $keys[$kid]);

        $issuer = $payload['iss'] ?? null;
        if (! is_string($issuer) || ! in_array($issuer, self::ALLOWED_ISSUERS, true)) {
            throw new InvalidArgumentException('Google ID token issuer is invalid.');
        }

        if (($payload['aud'] ?? null) !== $clientId) {
            throw new InvalidArgumentException('Google ID token audience is invalid.');
        }

        if (! isset($payload['exp']) || ! is_numeric($payload['exp']) || (int) $payload['exp'] <= time()) {
            throw new ExpiredException('Google ID token is expired.');
        }

        if (! isset($payload['iat']) || ! is_numeric($payload['iat'])) {
            throw new InvalidArgumentException('Google ID token issued-at claim is missing.');
        }

        if (($payload['email_verified'] ?? null) !== true) {
            throw new InvalidArgumentException('Google email is not verified.');
        }

        $subject = $payload['sub'] ?? null;
        if (! is_string($subject) || trim($subject) === '' || strlen($subject) > 255) {
            throw new InvalidArgumentException('Google ID token subject is invalid.');
        }

        $email = $payload['email'] ?? null;
        if (! is_string($email)
            || strlen($email) > 255
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Google ID token email is invalid.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, Key>
     */
    private function parseKeySet(array $jwks): array
    {
        try {
            return JWK::parseKeySet($jwks, 'RS256');
        } catch (InvalidArgumentException|UnexpectedValueException|\DomainException $e) {
            throw new RuntimeException('Google signing keys could not be parsed.', 0, $e);
        }
    }

    private function readHeader(string $idToken): \stdClass
    {
        $segments = explode('.', $idToken);
        if (count($segments) !== 3 || $segments[0] === '') {
            throw new InvalidArgumentException('Google ID token is malformed.');
        }

        $encodedHeader = strtr($segments[0], '-_', '+/');
        $encodedHeader .= str_repeat('=', (4 - strlen($encodedHeader) % 4) % 4);
        $json = base64_decode($encodedHeader, true);
        if ($json === false) {
            throw new InvalidArgumentException('Google ID token header is malformed.');
        }

        try {
            $header = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Google ID token header is malformed.', 0, $e);
        }

        if (! $header instanceof \stdClass) {
            throw new InvalidArgumentException('Google ID token header is malformed.');
        }

        return $header;
    }
}
