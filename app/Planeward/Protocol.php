<?php

namespace App\Planeward;

/**
 * Planeward Community Apps contract. Mirrors `protocol.ts` in
 * `@planeward/app-sdk`; keep both in sync.
 */
final class Protocol
{
    public const APP_SECRET_PREFIX = 'pwa_';

    /** App backends send this header with `Authorization: Bearer pwa_…`. */
    public const INSTALLATION_HEADER = 'x-planeward-installation';

    /** JWKS path on the Planeward app origin. */
    public const JWKS_PATH = '/api/auth/jwks';

    public const SIGNATURE_HEADER = 'x-planeward-signature';

    /** Stable per delivery; dedupe retried deliveries on it. */
    public const DELIVERY_HEADER = 'x-planeward-delivery';

    public const EVENT_HEADER = 'x-planeward-event';

    /** Planeward webhook bodies are small; refuse anything larger before reading it. */
    public const MAX_WEBHOOK_BODY_BYTES = 1024 * 1024;
}
