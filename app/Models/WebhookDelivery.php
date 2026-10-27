<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Webhook delivery log; the primary key dedupes `x-planeward-delivery`.
 *
 * @property string $delivery_id
 * @property string $event
 * @property string $installation_id
 * @property CarbonImmutable $received_at
 */
class WebhookDelivery extends Model
{
    protected $primaryKey = 'delivery_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
        ];
    }
}
