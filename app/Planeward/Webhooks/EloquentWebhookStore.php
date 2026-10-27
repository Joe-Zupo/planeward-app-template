<?php

namespace App\Planeward\Webhooks;

use App\Models\Installation;
use App\Models\WebhookDelivery;
use Closure;
use Illuminate\Support\Facades\DB;

class EloquentWebhookStore implements WebhookStore
{
    public function claimDelivery(string $deliveryId, string $event, string $installationId): bool
    {
        return WebhookDelivery::query()->insertOrIgnore([
            'delivery_id' => $deliveryId,
            'event' => $event,
            'installation_id' => $installationId,
            'received_at' => now(),
        ]) > 0;
    }

    public function releaseDelivery(string $deliveryId): void
    {
        WebhookDelivery::query()->whereKey($deliveryId)->delete();
    }

    public function updateInstallation(string $installationId, Closure $apply): void
    {
        DB::transaction(function () use ($installationId, $apply) {
            if (DB::getDriverName() === 'pgsql') {
                // Serializes concurrent deliveries for one installation,
                // including the first ones, when there is no row yet to lock
                // FOR UPDATE.
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$installationId]);
            }

            $existing = Installation::query()->find($installationId);
            $next = $apply($existing ? self::toRecord($existing) : null);

            Installation::query()->updateOrCreate(['id' => $next->id], [
                'workspace_id' => $next->workspaceId,
                'workspace_name' => $next->workspaceName,
                'granted_permissions' => $next->grantedPermissions,
                'installed_at' => $next->installedAt,
                'uninstalled_at' => $next->uninstalledAt,
            ]);
        });
    }

    private static function toRecord(Installation $row): InstallationRecord
    {
        return new InstallationRecord(
            id: $row->id,
            workspaceId: $row->workspace_id,
            workspaceName: $row->workspace_name,
            grantedPermissions: $row->granted_permissions,
            installedAt: $row->installed_at,
            uninstalledAt: $row->uninstalled_at,
        );
    }
}
