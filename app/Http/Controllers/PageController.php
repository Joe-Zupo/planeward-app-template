<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the React pages. The experience and dashboard pages get no server
 * data: they authenticate every API call with the viewer token from the host.
 */
class PageController extends Controller
{
    /** Env vars the app needs. The setup page shows set/unset only, never values. */
    private const REQUIRED_ENV = [
        'PLANEWARD_APP_ID' => 'planeward.app_id',
        'PLANEWARD_APP_SECRET' => 'planeward.app_secret',
        'PLANEWARD_WEBHOOK_SECRET' => 'planeward.webhook_secret',
        'PLANEWARD_BASE_URL' => 'planeward.base_url',
        'DB_URL' => 'database.connections.pgsql.url',
    ];

    public function setup(Request $request): View
    {
        $env = [];
        foreach (self::REQUIRED_ENV as $name => $key) {
            $env[] = ['name' => $name, 'set' => trim((string) config($key)) !== ''];
        }

        return $this->page('setup', 'Setup', [
            'origin' => $request->getSchemeAndHttpHost(),
            'env' => $env,
        ]);
    }

    public function experience(): View
    {
        return $this->page('experience', 'Experience');
    }

    public function dashboard(): View
    {
        return $this->page('dashboard', 'Dashboard');
    }

    private function page(string $name, string $title, array $props = []): View
    {
        return view('app', ['page' => $name, 'title' => $title, 'props' => $props]);
    }
}
