<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireViewer;
use App\Models\Installation;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\SignsViewerTokens;
use Tests\TestCase;

class RequireViewerTest extends TestCase
{
    use RefreshDatabase;
    use SignsViewerTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpViewerTokens();
    }

    public function test_reads_the_bearer_token_with_any_scheme_casing(): void
    {
        $token = fn (?string $header) => RequireViewer::bearerToken(
            Request::create('/api/posts', server: $header === null ? [] : ['HTTP_AUTHORIZATION' => $header])
        );

        $this->assertSame('abc', $token('Bearer abc'));
        $this->assertSame('abc', $token('bearer abc'));
        $this->assertSame('abc', $token('BEARER   abc  '));
        $this->assertNull($token('Basic abc'));
        $this->assertNull($token('Bearer'));
        $this->assertNull($token('Bearer a b'));
        $this->assertNull($token(null));
    }

    public function test_401s_without_a_token(): void
    {
        $this->getJson('/api/posts')
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Missing viewer token'])
            ->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_401s_a_garbage_token_with_a_generic_message(): void
    {
        $this->getJson('/api/posts', ['Authorization' => 'Bearer not.a.jwt'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Invalid viewer token']);
    }

    public function test_admits_a_valid_rs256_token(): void
    {
        $this->getJson('/api/posts', $this->asViewer())->assertOk();
    }

    public function test_401s_a_token_for_another_app_with_the_clear_aud_message(): void
    {
        $error = $this->getJson('/api/posts', $this->asViewer(['aud' => 'app_other']))
            ->assertUnauthorized()
            ->json('error');

        $this->assertStringContainsString('app_other', $error);
        $this->assertStringContainsString(self::APP_ID, $error);
        $this->assertStringContainsString('PLANEWARD_APP_ID', $error);
    }

    public function test_401s_expired_tokens_and_foreign_issuers_generically(): void
    {
        foreach ([['exp' => time() - 60], ['iss' => 'https://evil.test']] as $claims) {
            $this->getJson('/api/posts', $this->asViewer($claims))
                ->assertUnauthorized()
                ->assertExactJson(['error' => 'Invalid viewer token']);
        }
    }

    public function test_401s_a_token_signed_by_another_key(): void
    {
        $forged = $this->signToken(key: self::newPrivateKey());

        $this->getJson('/api/posts', ['Authorization' => "Bearer {$forged}"])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Invalid viewer token']);
    }

    public function test_401s_a_token_missing_required_claims(): void
    {
        $token = $this->signToken(['installationId' => null]);

        $this->getJson('/api/posts', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Invalid viewer token']);
    }

    public function test_401s_an_hs256_token_signed_with_the_public_key(): void
    {
        // Algorithm confusion: only RS256 keys from the JWKS are accepted.
        $header = JWT::urlsafeB64Encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => self::KID]));
        $payload = JWT::urlsafeB64Encode(json_encode(['iss' => self::BASE_URL, 'aud' => self::APP_ID]));
        $signature = JWT::urlsafeB64Encode(hash_hmac('sha256', "{$header}.{$payload}", 'public', true));

        $this->getJson('/api/posts', ['Authorization' => "Bearer {$header}.{$payload}.{$signature}"])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Invalid viewer token']);
    }

    public function test_403s_a_member_when_admin_is_required_and_admits_an_admin(): void
    {
        $this->getJson('/api/dashboard', $this->asViewer())
            ->assertForbidden()
            ->assertExactJson(['error' => 'Staff only']);

        $this->getJson('/api/dashboard', $this->asViewer(['role' => 'admin']))->assertOk();
    }

    public function test_401s_tokens_for_an_uninstalled_installation(): void
    {
        Installation::query()->create([
            'id' => 'inst_1',
            'workspace_id' => 'ws_1',
            'workspace_name' => 'Harbor',
            'granted_permissions' => [],
            'installed_at' => now()->subDay(),
            'uninstalled_at' => now(),
        ]);

        $this->getJson('/api/posts', $this->asViewer())
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'This app was uninstalled from the community.']);
    }
}
