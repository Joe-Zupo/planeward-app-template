<?php

namespace App\Planeward\Webhooks;

use Closure;

/** Storage the webhook handler needs. {@see EloquentWebhookStore} backs it with the database. */
interface WebhookStore
{
    /** Insert the delivery row; false when it already exists (a retry we've handled). */
    public function claimDelivery(string $deliveryId, string $event, string $installationId): bool;

    /** Delete the delivery row so Planeward's retry is processed again. */
    public function releaseDelivery(string $deliveryId): void;

    /**
     * Read-modify-write one installation row atomically (serialized per id).
     *
     * @param  Closure(InstallationRecord|null): InstallationRecord  $apply  receives the stored row, or null, and returns the row to store
     */
    public function updateInstallation(string $installationId, Closure $apply): void;
}
