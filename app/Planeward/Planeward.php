<?php

namespace App\Planeward;

use RuntimeException;

/** Entry point for server-side Planeward helpers. Reads `config/planeward.php`. */
class Planeward
{
    /**
     * Planeward API client acting on the viewer's installation. The
     * installation id comes from the verified token only, never from request
     * input.
     */
    public static function forViewer(Viewer $viewer): PlanewardClient
    {
        $secret = (string) config('planeward.app_secret');
        if (! str_starts_with($secret, Protocol::APP_SECRET_PREFIX)) {
            throw new RuntimeException('PLANEWARD_APP_SECRET must be set to the app secret (pwa_...).');
        }

        return new PlanewardClient($secret, $viewer->installationId, config('planeward.base_url'));
    }

    public static function verifier(): ViewerTokenVerifier
    {
        $appId = (string) config('planeward.app_id');
        if ($appId === '') {
            throw new RuntimeException('PLANEWARD_APP_ID is not set.');
        }

        return new ViewerTokenVerifier($appId, config('planeward.base_url'));
    }
}
