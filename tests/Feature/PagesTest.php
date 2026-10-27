<?php

namespace Tests\Feature;

use Tests\TestCase;

class PagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pages_render_without_cookies_or_frame_blocking_headers(): void
    {
        foreach (['/', '/experience', '/dashboard'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertEmpty($response->headers->getCookies(), "{$path} set a cookie");
            $this->assertFalse($response->headers->has('X-Frame-Options'), "{$path} set X-Frame-Options");
        }
    }

    public function test_setup_page_reports_env_vars_without_their_values(): void
    {
        config(['planeward.app_id' => 'app_secret_looking_id', 'planeward.app_secret' => '']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-page="setup"', false)
            ->assertSee('{&quot;name&quot;:&quot;PLANEWARD_APP_ID&quot;,&quot;set&quot;:true}', false)
            ->assertSee('{&quot;name&quot;:&quot;PLANEWARD_APP_SECRET&quot;,&quot;set&quot;:false}', false)
            ->assertDontSee('app_secret_looking_id');
    }
}
