<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Example feature. Always scope queries by installation_id + channel_id from
 * the verified viewer.
 *
 * @property string $id
 * @property string $installation_id
 * @property string $channel_id
 * @property string $author_id
 * @property string $author_name
 * @property string $body
 * @property CarbonImmutable $created_at
 */
class Post extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
