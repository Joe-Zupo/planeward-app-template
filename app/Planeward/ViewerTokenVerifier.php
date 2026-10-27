<?php

namespace App\Planeward;

use App\Planeward\Exceptions\TokenAudienceException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Verifies viewer tokens from the iframe against Planeward's JWKS: RS256
 * signature, issuer = `PLANEWARD_BASE_URL`, `aud` = `PLANEWARD_APP_ID`, expiry.
 */
class ViewerTokenVerifier
{
    /** How long a fetched JWKS is reused. */
    private const JWKS_TTL_SECONDS = 600;

    /** Minimum gap between refetches triggered by an unknown `kid`. */
    private const JWKS_COOLDOWN_SECONDS = 30;

    public function __construct(
        private readonly string $appId,
        private readonly string $baseUrl,
    ) {}

    /**
     * @throws TokenAudienceException when the token is valid but for another app
     * @throws \Throwable for every other failure (bad signature, expired, wrong issuer, missing claims, JWKS unreachable)
     */
    public function verify(string $token): Viewer
    {
        $payload = (array) json_decode(
            json_encode(JWT::decode($token, $this->keysFor($token))),
            true,
        );

        if (($payload['iss'] ?? null) !== $this->baseUrl) {
            throw new UnexpectedValueException('Unexpected "iss" claim value');
        }

        $audiences = (array) ($payload['aud'] ?? []);
        if (! in_array($this->appId, $audiences, true)) {
            // The signature is already checked, so the audience is safe to echo.
            throw new TokenAudienceException(
                $audiences === [] ? '(none)' : implode(', ', $audiences),
                $this->appId,
            );
        }
        $payload['aud'] = $this->appId;

        return Viewer::fromClaims($payload)
            ?? throw new UnexpectedValueException('Planeward token is missing required claims.');
    }

    /**
     * RS256 keys from the cached JWKS. Refetches once (rate limited) when the
     * token names a `kid` the cached set does not have, so key rotation works.
     *
     * @return array<string, Key>
     */
    private function keysFor(string $token): array
    {
        $keys = $this->parse(Cache::remember($this->cacheKey(), self::JWKS_TTL_SECONDS, $this->fetchJwks(...)));

        $kid = $this->unverifiedKid($token);
        if ($kid !== null && ! isset($keys[$kid]) && Cache::add($this->cacheKey().':cooldown', true, self::JWKS_COOLDOWN_SECONDS)) {
            $jwks = $this->fetchJwks();
            Cache::put($this->cacheKey(), $jwks, self::JWKS_TTL_SECONDS);
            $keys = $this->parse($jwks);
        }

        return $keys;
    }

    private function fetchJwks(): array
    {
        return Http::acceptJson()
            ->timeout(5)
            ->get($this->baseUrl.Protocol::JWKS_PATH)
            ->throw()
            ->json();
    }

    /** @return array<string, Key> */
    private function parse(array $jwks): array
    {
        return array_filter(
            JWK::parseKeySet($jwks, 'RS256'),
            fn (Key $key) => $key->getAlgorithm() === 'RS256',
        );
    }

    private function unverifiedKid(string $token): ?string
    {
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0]), true);

        return is_array($header) && is_string($header['kid'] ?? null) ? $header['kid'] : null;
    }

    private function cacheKey(): string
    {
        return 'planeward:jwks:'.sha1($this->baseUrl);
    }
}
