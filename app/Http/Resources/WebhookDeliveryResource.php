<?php

namespace App\Http\Resources;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebhookDelivery */
class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'deliveryId' => $this->delivery_id,
            'event' => $this->event,
            'installationId' => $this->installation_id,
            'receivedAt' => $this->received_at->toIso8601ZuluString('millisecond'),
        ];
    }
}
