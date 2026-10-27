<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsViewerTokens;
use Tests\TestCase;

class PlanewardApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsViewerTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpViewerTokens();
    }

    public function test_lists_members_with_the_app_secret_for_the_viewers_installation(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/community/members*' => Http::response([
                'data' => [],
                'meta' => ['total' => 0, 'limit' => 50, 'offset' => 0],
            ]),
        ]);

        $this->getJson('/api/members', $this->asViewer(['role' => 'admin']))
            ->assertOk()
            ->assertJsonPath('meta.limit', 50);

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE_URL.'/api/v1/community/members?limit=50'
            && $request->hasHeader('Authorization', 'Bearer pwa_test')
            && $request->hasHeader('x-planeward-installation', 'inst_1'));
    }

    public function test_announces_as_the_app_and_passes_planeward_errors_through(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/community/channels/chan_9/messages' => Http::sequence()
                ->push(['data' => ['id' => 'msg_1', 'body' => 'Hi']], 201)
                ->push(['error' => 'Channel not found'], 404),
        ]);
        $admin = $this->asViewer(['role' => 'admin']);

        $this->postJson('/api/announce', ['channelId' => 'chan_9', 'body' => 'Hi'], $admin)
            ->assertCreated()
            ->assertJsonPath('data.id', 'msg_1');

        $this->postJson('/api/announce', ['channelId' => 'chan_9', 'body' => 'Hi'], $admin)
            ->assertNotFound()
            ->assertExactJson(['error' => 'Channel not found']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->data() === ['body' => 'Hi']
            && $request->hasHeader('x-planeward-installation', 'inst_1'));
    }

    public function test_announce_is_staff_only_and_validates_input(): void
    {
        $this->postJson('/api/announce', ['channelId' => 'chan_9', 'body' => 'Hi'], $this->asViewer())
            ->assertForbidden();

        $this->postJson('/api/announce', ['body' => 'Hi'], $this->asViewer(['role' => 'admin']))
            ->assertBadRequest()
            ->assertExactJson(['error' => 'Enter a channel id.']);
    }
}
