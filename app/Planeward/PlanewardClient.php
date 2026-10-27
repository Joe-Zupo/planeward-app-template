<?php

namespace App\Planeward;

use App\Planeward\Exceptions\PlanewardApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Planeward API client acting on one installation. Build it with
 * {@see Planeward::forViewer()} so the installation id comes from the verified
 * token, never from request input.
 *
 * Responses are the decoded JSON: `{ data, meta: { total, limit, offset } }`
 * for lists and `{ data }` for single records.
 */
class PlanewardClient
{
    public function __construct(
        /** App secret (`pwa_…`). Keep it on your server. */
        private readonly string $secret,
        /** Installation to act on, from the viewer token or a webhook. */
        private readonly string $installationId,
        private readonly string $baseUrl,
    ) {}

    /**
     * Community members (needs COMMUNITY_MEMBERS_READ).
     *
     * @param  array{limit?: int, offset?: int, search?: string}  $params
     */
    public function listMembers(array $params = []): array
    {
        return $this->request('GET', '/api/v1/community/members', query: array_filter(
            $params,
            fn ($value) => $value !== null && $value !== '',
        ));
    }

    /** One community member (needs COMMUNITY_MEMBERS_READ). */
    public function getMember(string $memberId): array
    {
        return $this->request('GET', '/api/v1/community/members/'.rawurlencode($memberId));
    }

    /**
     * Post a message as the app (needs COMMUNITY_MESSAGES_WRITE).
     *
     * @param  array{body: string, replyToId?: string}  $input  `body` is 1 to 4000 characters.
     */
    public function createMessage(string $channelId, array $input): array
    {
        return $this->request('POST', '/api/v1/community/channels/'.rawurlencode($channelId).'/messages', body: $input);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $method === 'GET'
            ? $this->http()->get($path, $query)
            : $this->http()->post($path, $body ?? []);

        $json = $response->json();

        if ($response->failed()) {
            $message = is_array($json) && is_string($json['error'] ?? null)
                ? $json['error']
                : "Planeward API request failed with status {$response->status()}.";

            throw new PlanewardApiException($response->status(), $message);
        }

        return is_array($json) ? $json : [];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->secret)
            ->withHeaders([Protocol::INSTALLATION_HEADER => $this->installationId])
            ->acceptJson()
            ->timeout(10);
    }
}
