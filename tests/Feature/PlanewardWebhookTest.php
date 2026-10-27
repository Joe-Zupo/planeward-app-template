<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\WebhookDelivery;
use App\Planeward\Webhooks\EloquentWebhookStore;
use App\Planeward\Webhooks\WebhookReceived;
use App\Planeward\Webhooks\WebhookStore;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PlanewardWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['planeward.webhook_secret' => self::SECRET]);
    }

    private static function installed(string $installedAt = '2026-10-01T10:00:00.000Z'): array
    {
        return [
            'event' => 'app.installed',
            'data' => [
                'installationId' => 'inst_1',
                'workspaceId' => 'ws_1',
                'workspaceName' => 'Harbor',
                'grantedPermissions' => ['COMMUNITY_MEMBERS_READ'],
                'installedAt' => $installedAt,
            ],
            'timestamp' => $installedAt,
            'installationId' => 'inst_1',
            'workspaceId' => 'ws_1',
        ];
    }

    private static function uninstalled(string $uninstalledAt = '2026-10-02T10:00:00.000Z'): array
    {
        return [
            'event' => 'app.uninstalled',
            'data' => ['installationId' => 'inst_1', 'workspaceId' => 'ws_1', 'uninstalledAt' => $uninstalledAt],
            'timestamp' => $uninstalledAt,
            'installationId' => 'inst_1',
            'workspaceId' => 'ws_1',
        ];
    }

    private function send(
        array|string $body,
        ?string $deliveryId = 'dlv_1',
        string $secret = self::SECRET,
        ?string $signature = null,
    ): TestResponse {
        $rawBody = is_string($body) ? $body : json_encode($body);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Planeward-Signature' => $signature ?? 'sha256='.hash_hmac('sha256', $rawBody, $secret),
        ];
        if ($deliveryId !== null) {
            $headers['X-Planeward-Delivery'] = $deliveryId;
        }

        return $this->call('POST', '/api/webhooks/planeward', server: $this->transformHeadersToServerVars($headers), content: $rawBody);
    }

    private function onEvent(Closure $listener): void
    {
        Event::listen(WebhookReceived::class, $listener);
    }

    public function test_401s_a_bad_signature_without_touching_the_store_or_handler(): void
    {
        $called = false;
        $this->onEvent(function () use (&$called) {
            $called = true;
        });

        $this->send(self::installed(), secret: 'wrong')->assertUnauthorized();
        $this->send(self::installed(), signature: 'sha256=00')->assertUnauthorized();
        $this->send(self::installed(), signature: '')->assertUnauthorized();

        $this->assertSame(0, WebhookDelivery::query()->count());
        $this->assertSame(0, Installation::query()->count());
        $this->assertFalse($called);
    }

    public function test_401s_a_body_changed_after_signing(): void
    {
        $signature = 'sha256='.hash_hmac('sha256', json_encode(self::installed()), self::SECRET);
        $tampered = self::installed();
        $tampered['data']['workspaceName'] = 'Evil';

        $this->send($tampered, signature: $signature)->assertUnauthorized();
    }

    public function test_400s_a_signed_delivery_without_the_delivery_header(): void
    {
        $this->send(self::installed(), deliveryId: null)->assertBadRequest();

        $this->assertSame(0, WebhookDelivery::query()->count());
    }

    public function test_400s_signed_but_malformed_payloads_before_claiming_the_delivery(): void
    {
        $otherInstallation = self::installed();
        $otherInstallation['installationId'] = 'inst_other';

        foreach ([
            'not json',
            ['event' => 'app.installed'],
            [...self::installed(), 'data' => ['installationId' => 'inst_1']],
            // data must match the envelope it is signed with
            $otherInstallation,
        ] as $body) {
            $this->send($body)->assertBadRequest();
        }

        $this->assertSame(0, WebhookDelivery::query()->count());
        $this->assertSame(0, Installation::query()->count());
    }

    public function test_204s_the_first_delivery_upserts_the_installation_then_dispatches(): void
    {
        $seen = [];
        $this->onEvent(function (WebhookReceived $received) use (&$seen) {
            // Persistence runs before the app's listeners.
            $this->assertTrue(Installation::query()->whereKey('inst_1')->exists());
            $seen[] = $received->envelope->event;
        });

        $this->send(self::installed())->assertNoContent();

        $row = Installation::query()->findOrFail('inst_1');
        $this->assertSame('ws_1', $row->workspace_id);
        $this->assertSame('Harbor', $row->workspace_name);
        $this->assertSame(['COMMUNITY_MEMBERS_READ'], $row->granted_permissions);
        $this->assertTrue($row->installed_at->equalTo('2026-10-01T10:00:00.000Z'));
        $this->assertNull($row->uninstalled_at);
        $this->assertSame(['app.installed'], $seen);
        $this->assertTrue(WebhookDelivery::query()->whereKey('dlv_1')->exists());
    }

    public function test_200s_a_duplicate_delivery_without_rerunning_the_handler(): void
    {
        $calls = 0;
        $this->onEvent(function () use (&$calls) {
            $calls++;
        });

        $this->send(self::installed());
        $this->send(self::installed())->assertOk()->assertExactJson(['duplicate' => true]);

        $this->assertSame(1, $calls);
    }

    public function test_sets_uninstalled_at_on_app_uninstalled(): void
    {
        $this->send(self::installed());
        $this->send(self::uninstalled(), deliveryId: 'dlv_2')->assertNoContent();

        $this->assertTrue(Installation::query()->findOrFail('inst_1')->uninstalled_at->equalTo('2026-10-02T10:00:00.000Z'));
    }

    public function test_does_not_resurrect_when_app_installed_arrives_after_app_uninstalled(): void
    {
        $this->send(self::uninstalled(), deliveryId: 'dlv_2')->assertNoContent();
        $this->send(self::installed(), deliveryId: 'dlv_1')->assertNoContent();

        $row = Installation::query()->findOrFail('inst_1');
        $this->assertTrue($row->uninstalled_at->equalTo('2026-10-02T10:00:00.000Z'));
        // The late install still fills in the real details.
        $this->assertSame('Harbor', $row->workspace_name);
        $this->assertTrue($row->installed_at->equalTo('2026-10-01T10:00:00.000Z'));
    }

    public function test_does_not_resurrect_on_a_replayed_app_installed_under_a_new_delivery_id(): void
    {
        $this->send(self::installed(), deliveryId: 'a');
        $this->send(self::uninstalled(), deliveryId: 'b');
        $this->send(self::installed(), deliveryId: 'c');

        $this->assertNotNull(Installation::query()->findOrFail('inst_1')->uninstalled_at);
    }

    public function test_releases_the_delivery_and_500s_when_a_listener_throws_so_the_retry_runs(): void
    {
        $attempts = 0;
        $this->onEvent(function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('boom');
            }
        });

        $this->send(self::installed())
            ->assertStatus(500)
            ->assertExactJson(['error' => 'Webhook handling failed']);
        $this->assertFalse(WebhookDelivery::query()->whereKey('dlv_1')->exists());

        $this->send(self::installed())->assertNoContent();
        $this->assertSame(2, $attempts);
    }

    public function test_releases_the_delivery_and_500s_when_persistence_throws(): void
    {
        $this->app->bind(WebhookStore::class, fn () => new class extends EloquentWebhookStore
        {
            public function updateInstallation(string $installationId, Closure $apply): void
            {
                throw new RuntimeException('db down');
            }
        });

        $this->send(self::installed())->assertStatus(500);

        $this->assertSame(0, WebhookDelivery::query()->count());
    }

    public function test_claims_and_dispatches_events_it_does_not_persist(): void
    {
        $seen = [];
        $this->onEvent(function (WebhookReceived $received) use (&$seen) {
            $seen[] = $received->envelope->event;
        });

        $this->send([
            'event' => 'community.membership.created',
            'data' => ['id' => 'cm_1'],
            'timestamp' => '2026-10-01T10:00:00.000Z',
            'installationId' => 'inst_1',
            'workspaceId' => 'ws_1',
        ])->assertNoContent();

        $this->assertSame(['community.membership.created'], $seen);
        $this->assertSame(0, Installation::query()->count());
    }
}
