<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installationId' => $this->installation_id,
            'channelId' => $this->channel_id,
            'authorId' => $this->author_id,
            'authorName' => $this->author_name,
            'body' => $this->body,
            'createdAt' => $this->created_at->toIso8601ZuluString('millisecond'),
        ];
    }
}
