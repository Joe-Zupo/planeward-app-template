<?php

namespace App\Planeward\Webhooks;

/** A verified Planeward webhook delivery body. */
final readonly class WebhookEnvelope
{
    public function __construct(
        public string $event,
        public mixed $data,
        public string $timestamp,
        public string $installationId,
        public string $workspaceId,
    ) {}

    /** Null when the body is not a webhook envelope. */
    public static function parse(string $rawBody): ?self
    {
        $value = json_decode($rawBody, true);

        $valid = is_array($value) && ! array_is_list($value)
            && is_string($value['event'] ?? null)
            && array_key_exists('data', $value)
            && is_string($value['timestamp'] ?? null)
            && is_string($value['installationId'] ?? null)
            && is_string($value['workspaceId'] ?? null);

        if (! $valid) {
            return null;
        }

        return new self(
            event: $value['event'],
            data: $value['data'],
            timestamp: $value['timestamp'],
            installationId: $value['installationId'],
            workspaceId: $value['workspaceId'],
        );
    }
}
