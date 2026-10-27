<?php

namespace App\Planeward\Webhooks;

use Carbon\CarbonImmutable;

/** An `installations` row as the webhook merge rules see it. */
final readonly class InstallationRecord
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $workspaceName,
        /** @var list<string> */
        public array $grantedPermissions,
        public CarbonImmutable $installedAt,
        public ?CarbonImmutable $uninstalledAt,
    ) {}

    public function withUninstalledAt(?CarbonImmutable $uninstalledAt): self
    {
        return new self(
            $this->id,
            $this->workspaceId,
            $this->workspaceName,
            $this->grantedPermissions,
            $this->installedAt,
            $uninstalledAt,
        );
    }
}
