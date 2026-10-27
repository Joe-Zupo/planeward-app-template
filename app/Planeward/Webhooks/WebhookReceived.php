<?php

namespace App\Planeward\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a verified, first-time delivery is persisted. Listen for it
 * to react to events such as `community.membership.*`. Listeners run inside
 * the webhook request: throwing makes Planeward retry the delivery. A queued
 * listener (`ShouldQueue`) does not, so make it idempotent instead.
 */
class WebhookReceived
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly string $deliveryId,
    ) {}
}
