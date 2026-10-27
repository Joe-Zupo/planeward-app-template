<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private static function columns(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        sort($columns);

        return $columns;
    }

    private static function primaryKey(string $table): array
    {
        return collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [];
    }

    public function test_installations_are_keyed_by_installation_id(): void
    {
        $this->assertSame([
            'granted_permissions',
            'id',
            'installed_at',
            'uninstalled_at',
            'workspace_id',
            'workspace_name',
        ], self::columns('installations'));
        $this->assertSame(['id'], self::primaryKey('installations'));

        $uninstalledAt = collect(Schema::getColumns('installations'))->firstWhere('name', 'uninstalled_at');
        $this->assertTrue($uninstalledAt['nullable']);
    }

    public function test_webhook_deliveries_dedupe_on_delivery_id(): void
    {
        $this->assertSame(['delivery_id', 'event', 'installation_id', 'received_at'], self::columns('webhook_deliveries'));
        $this->assertSame(['delivery_id'], self::primaryKey('webhook_deliveries'));
    }

    public function test_posts_are_indexed_by_installation_channel_and_time(): void
    {
        $this->assertSame([
            'author_id',
            'author_name',
            'body',
            'channel_id',
            'created_at',
            'id',
            'installation_id',
        ], self::columns('posts'));
        $this->assertSame(['id'], self::primaryKey('posts'));

        $index = collect(Schema::getIndexes('posts'))->firstWhere('name', 'posts_installation_channel_created_idx');
        $this->assertSame(['installation_id', 'channel_id', 'created_at'], $index['columns']);
    }
}
