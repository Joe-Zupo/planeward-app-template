<?php

namespace Tests\Concerns;

use App\Planeward\Protocol;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Signs RS256 tokens shaped like Planeward viewer tokens and serves the
 * matching JWKS through Http::fake(), so tests run without Planeward.
 */
trait SignsViewerTokens
{
    protected const APP_ID = 'app_template';

    protected const BASE_URL = 'https://planeward.test';

    protected const KID = 'test-key';

    private static ?OpenSSLAsymmetricKey $privateKey = null;

    protected function setUpViewerTokens(): void
    {
        config([
            'planeward.app_id' => self::APP_ID,
            'planeward.app_secret' => 'pwa_test',
            'planeward.webhook_secret' => 'whsec_test',
            'planeward.base_url' => self::BASE_URL,
        ]);

        Http::fake([
            self::BASE_URL.Protocol::JWKS_PATH => Http::response(['keys' => [self::jwk(self::privateKey())]]),
        ]);
    }

    protected function signToken(array $claims = [], ?OpenSSLAsymmetricKey $key = null): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::BASE_URL,
            'aud' => self::APP_ID,
            'sub' => 'cm_1',
            'iat' => $now,
            'exp' => $now + 300,
            'workspaceId' => 'ws_1',
            'installationId' => 'inst_1',
            'channelId' => 'chan_1',
            'view' => 'experience',
            'role' => 'member',
            'name' => 'Ada',
            'avatar' => null,
            ...$claims,
        ], $key ?? self::privateKey(), 'RS256', self::KID);
    }

    /** Headers for a request as the given viewer. */
    protected function asViewer(array $claims = []): array
    {
        return ['Authorization' => 'Bearer '.$this->signToken($claims)];
    }

    protected static function newPrivateKey(): OpenSSLAsymmetricKey
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        // Windows PHP builds ship openssl.cnf next to php.exe but do not point
        // OpenSSL at it, so key generation fails without this.
        $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (! getenv('OPENSSL_CONF') && is_file($bundledConfig)) {
            $options['config'] = $bundledConfig;
        }

        return openssl_pkey_new($options);
    }

    private static function privateKey(): OpenSSLAsymmetricKey
    {
        return self::$privateKey ??= self::newPrivateKey();
    }

    private static function jwk(OpenSSLAsymmetricKey $key): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];

        return [
            'kty' => 'RSA',
            'kid' => self::KID,
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => JWT::urlsafeB64Encode($rsa['n']),
            'e' => JWT::urlsafeB64Encode($rsa['e']),
        ];
    }
}
