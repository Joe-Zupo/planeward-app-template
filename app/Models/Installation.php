<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Planeward installation, keyed by installationId. Written only by
 * the webhook handler.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $workspace_name
 * @property list<string> $granted_permissions
 * @property CarbonImmutable $installed_at
 * @property CarbonImmutable|null $uninstalled_at
 */
class Installation extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'granted_permissions' => 'array',
            'installed_at' => 'immutable_datetime',
            'uninstalled_at' => 'immutable_datetime',
        ];
    }
}
