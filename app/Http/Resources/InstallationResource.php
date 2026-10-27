<?php

namespace App\Http\Resources;

use App\Models\Installation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Installation */
class InstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspaceId' => $this->workspace_id,
            'workspaceName' => $this->workspace_name,
            'grantedPermissions' => $this->granted_permissions,
            'installedAt' => $this->installed_at->toIso8601ZuluString('millisecond'),
            'uninstalledAt' => $this->uninstalled_at?->toIso8601ZuluString('millisecond'),
        ];
    }
}
