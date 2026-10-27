<?php

namespace App\Planeward\Webhooks;

use Carbon\CarbonImmutable;

/** How `app.installed` and `app.uninstalled` merge into the stored installation row. */
final class InstallationRules
{
    /**
     * Next row for `app.installed`. A stored uninstall at or after this install
     * wins, so a late or replayed `app.installed` never resurrects an
     * uninstalled installation, and an install older than the stored one never
     * overwrites its details. The uninstall-first placeholder
     * (`workspaceName: ""`) is always filled in.
     *
     * @param  array{installationId: string, workspaceId: string, workspaceName: string, grantedPermissions: list<string>, installedAt: string}  $data
     */
    public static function applyInstalled(?InstallationRecord $existing, array $data): InstallationRecord
    {
        $installedAt = CarbonImmutable::parse($data['installedAt']);
        $uninstalledAt = $existing?->uninstalledAt && $existing->uninstalledAt->greaterThanOrEqualTo($installedAt)
            ? $existing->uninstalledAt
            : null;

        $stale = $existing !== null
            && $existing->workspaceName !== ''
            && $existing->installedAt->greaterThan($installedAt);
        if ($stale) {
            return $existing->withUninstalledAt($uninstalledAt);
        }

        return new InstallationRecord(
            id: $data['installationId'],
            workspaceId: $data['workspaceId'],
            workspaceName: $data['workspaceName'],
            grantedPermissions: $data['grantedPermissions'],
            installedAt: $installedAt,
            uninstalledAt: $uninstalledAt,
        );
    }

    /**
     * Next row for `app.uninstalled`. With no row yet (uninstall delivered
     * before install) it stores a tombstone so the later `app.installed` stays
     * uninstalled.
     *
     * @param  array{installationId: string, workspaceId: string, uninstalledAt: string}  $data
     */
    public static function applyUninstalled(?InstallationRecord $existing, array $data): InstallationRecord
    {
        $uninstalledAt = CarbonImmutable::parse($data['uninstalledAt']);

        if ($existing === null) {
            return new InstallationRecord(
                id: $data['installationId'],
                workspaceId: $data['workspaceId'],
                workspaceName: '',
                grantedPermissions: [],
                // Placeholder until `app.installed` arrives with the real time.
                installedAt: $uninstalledAt,
                uninstalledAt: $uninstalledAt,
            );
        }

        return $existing->withUninstalledAt(
            $existing->uninstalledAt && $existing->uninstalledAt->greaterThan($uninstalledAt)
                ? $existing->uninstalledAt
                : $uninstalledAt,
        );
    }
}
