<?php

namespace Tests\Unit;

use App\Planeward\Webhooks\InstallationRecord;
use App\Planeward\Webhooks\InstallationRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class InstallationRulesTest extends TestCase
{
    private static function base(array $overrides = []): InstallationRecord
    {
        return new InstallationRecord(...[
            'id' => 'inst_1',
            'workspaceId' => 'ws_1',
            'workspaceName' => 'Harbor',
            'grantedPermissions' => [],
            'installedAt' => CarbonImmutable::parse('2026-10-01T10:00:00.000Z'),
            'uninstalledAt' => null,
            ...$overrides,
        ]);
    }

    private static function installed(string $installedAt = '2026-10-01T10:00:00.000Z', array $overrides = []): array
    {
        return [
            'installationId' => 'inst_1',
            'workspaceId' => 'ws_1',
            'workspaceName' => 'Harbor',
            'grantedPermissions' => ['COMMUNITY_MEMBERS_READ'],
            'installedAt' => $installedAt,
            ...$overrides,
        ];
    }

    private static function uninstalled(string $uninstalledAt): array
    {
        return ['installationId' => 'inst_1', 'workspaceId' => 'ws_1', 'uninstalledAt' => $uninstalledAt];
    }

    public function test_an_install_newer_than_the_stored_uninstall_clears_it(): void
    {
        $next = InstallationRules::applyInstalled(
            self::base(['uninstalledAt' => CarbonImmutable::parse('2026-10-01T09:00:00.000Z')]),
            self::installed('2026-10-01T10:00:00.000Z'),
        );

        $this->assertNull($next->uninstalledAt);
    }

    public function test_an_install_older_than_the_stored_one_keeps_the_stored_details(): void
    {
        $base = self::base(['workspaceName' => 'Harbor Studio', 'grantedPermissions' => ['A']]);

        $next = InstallationRules::applyInstalled($base, self::installed('2026-10-01T09:00:00.000Z', ['workspaceName' => 'Old']));

        $this->assertSame('Harbor Studio', $next->workspaceName);
        $this->assertSame(['A'], $next->grantedPermissions);
        $this->assertTrue($next->installedAt->equalTo($base->installedAt));
    }

    public function test_a_late_install_fills_in_the_uninstall_first_placeholder(): void
    {
        $placeholder = InstallationRules::applyUninstalled(null, self::uninstalled('2026-10-02T10:00:00.000Z'));

        $next = InstallationRules::applyInstalled($placeholder, self::installed());

        $this->assertNotSame('', $next->workspaceName);
        $this->assertTrue($next->uninstalledAt->equalTo($placeholder->uninstalledAt));
    }

    public function test_an_uninstall_never_moves_uninstalled_at_backwards(): void
    {
        $later = CarbonImmutable::parse('2026-10-03T00:00:00.000Z');

        $next = InstallationRules::applyUninstalled(
            self::base(['uninstalledAt' => $later]),
            self::uninstalled('2026-10-02T00:00:00.000Z'),
        );

        $this->assertTrue($next->uninstalledAt->equalTo($later));
    }
}
