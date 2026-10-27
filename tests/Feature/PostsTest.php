<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsViewerTokens;
use Tests\TestCase;

class PostsTest extends TestCase
{
    use RefreshDatabase;
    use SignsViewerTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpViewerTokens();
    }

    public function test_creates_a_post_scoped_by_the_verified_claims_not_the_body(): void
    {
        $this->postJson('/api/posts', [
            'body' => '  Hello channel  ',
            'installationId' => 'inst_evil',
            'channelId' => 'chan_evil',
            'authorId' => 'cm_evil',
        ], $this->asViewer())
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello channel')
            ->assertJsonPath('data.installationId', 'inst_1')
            ->assertJsonPath('data.channelId', 'chan_1')
            ->assertJsonPath('data.authorId', 'cm_1')
            ->assertJsonPath('data.authorName', 'Ada');
    }

    public function test_lists_only_the_viewers_installation_and_channel(): void
    {
        foreach ([['inst_1', 'chan_1'], ['inst_1', 'chan_2'], ['inst_2', 'chan_1']] as [$installation, $channel]) {
            Post::query()->create([
                'installation_id' => $installation,
                'channel_id' => $channel,
                'author_id' => 'cm_1',
                'author_name' => 'Ada',
                'body' => "{$installation}/{$channel}",
            ]);
        }

        $this->getJson('/api/posts', $this->asViewer())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'inst_1/chan_1');
    }

    public function test_rejects_an_empty_or_too_long_post(): void
    {
        $this->postJson('/api/posts', ['body' => '   '], $this->asViewer())
            ->assertBadRequest()
            ->assertExactJson(['error' => 'Write something first.']);

        $this->postJson('/api/posts', ['body' => str_repeat('a', 501)], $this->asViewer())
            ->assertBadRequest();
    }

    public function test_the_board_needs_a_channel(): void
    {
        $this->getJson('/api/posts', $this->asViewer(['channelId' => null, 'view' => 'dashboard']))
            ->assertBadRequest()
            ->assertExactJson(['error' => 'The board is only available in a channel.']);
    }
}
