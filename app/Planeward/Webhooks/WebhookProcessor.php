<?php

namespace App\Planeward\Webhooks;

use App\Http\ApiError;
use App\Planeward\Protocol;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verify, dedupe, persist and dispatch one Planeward webhook delivery.
 * Nothing in the body is trusted until the HMAC over the raw bytes checks out.
 *
 * - 401 bad signature, 400 missing `x-planeward-delivery` or bad payload;
 * - 200 `{ duplicate: true }` for a delivery already handled;
 * - persists `app.installed` / `app.uninstalled`, then dispatches
 *   {@see WebhookReceived}; 204;
 * - 500 if either throws (the delivery is released so the retry runs again).
 */
class WebhookProcessor
{
    private const ISO_DATETIME = 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/';

    public function __construct(
        private readonly WebhookStore $store,
    ) {}

    public function handle(Request $request): Response
    {
        $secret = (string) config('planeward.webhook_secret');

        if ((int) $request->headers->get('Content-Length') > Protocol::MAX_WEBHOOK_BODY_BYTES) {
            return ApiError::make(413, 'Payload too large');
        }

        $rawBody = $request->getContent();
        if (strlen($rawBody) > Protocol::MAX_WEBHOOK_BODY_BYTES) {
            return ApiError::make(413, 'Payload too large');
        }

        if ($secret === '' || ! self::verifySignature($rawBody, $request->headers->get(Protocol::SIGNATURE_HEADER), $secret)) {
            return ApiError::make(401, 'Invalid signature');
        }

        // Signed from here on.
        $deliveryId = trim((string) $request->headers->get(Protocol::DELIVERY_HEADER));
        if ($deliveryId === '') {
            return ApiError::make(400, 'Missing '.Protocol::DELIVERY_HEADER.' header');
        }

        $envelope = WebhookEnvelope::parse($rawBody);
        if ($envelope === null) {
            return ApiError::make(400, 'Invalid webhook payload');
        }

        $persist = $this->planPersistence($envelope);
        if ($persist === null) {
            return ApiError::make(400, "Invalid {$envelope->event} payload");
        }

        if (! $this->store->claimDelivery($deliveryId, $envelope->event, $envelope->installationId)) {
            return response()->json(['duplicate' => true]);
        }

        try {
            $persist();
            WebhookReceived::dispatch($envelope, $deliveryId);
        } catch (Throwable $error) {
            Log::error("[planeward] webhook {$envelope->event} ({$deliveryId}) failed", ['exception' => $error]);
            try {
                $this->store->releaseDelivery($deliveryId);
            } catch (Throwable $releaseError) {
                Log::error("[planeward] could not release delivery {$deliveryId}; its retry will be skipped", ['exception' => $releaseError]);
            }

            return ApiError::make(500, 'Webhook handling failed');
        }

        return response()->noContent();
    }

    /**
     * Check `x-planeward-signature` (`sha256=<hex>`) against an HMAC-SHA256 of
     * the raw body, in constant time.
     */
    public static function verifySignature(string $rawBody, ?string $signature, string $secret): bool
    {
        if ($signature === null || ! preg_match('/^sha256=([0-9a-f]{64})$/i', $signature, $matches)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), strtolower($matches[1]));
    }

    /** Validate built-in event data up front; null means "malformed". */
    private function planPersistence(WebhookEnvelope $envelope): ?Closure
    {
        $rules = match ($envelope->event) {
            'app.installed' => [
                'installationId' => ['required', 'string'],
                'workspaceId' => ['required', 'string'],
                'workspaceName' => ['present', 'string'],
                'grantedPermissions' => ['present', 'array', 'list'],
                'grantedPermissions.*' => ['string'],
                'installedAt' => ['required', 'string', self::ISO_DATETIME],
            ],
            'app.uninstalled' => [
                'installationId' => ['required', 'string'],
                'workspaceId' => ['required', 'string'],
                'uninstalledAt' => ['required', 'string', self::ISO_DATETIME],
            ],
            default => null,
        };

        if ($rules === null) {
            return fn () => null;
        }

        $data = $envelope->data;
        if (! is_array($data) || Validator::make($data, $rules)->fails()) {
            return null;
        }

        // Data must match the envelope it is signed with.
        if ($data['installationId'] !== $envelope->installationId || $data['workspaceId'] !== $envelope->workspaceId) {
            return null;
        }

        $apply = $envelope->event === 'app.installed'
            ? fn (?InstallationRecord $existing) => InstallationRules::applyInstalled($existing, $data)
            : fn (?InstallationRecord $existing) => InstallationRules::applyUninstalled($existing, $data);

        return fn () => $this->store->updateInstallation($envelope->installationId, $apply);
    }
}
