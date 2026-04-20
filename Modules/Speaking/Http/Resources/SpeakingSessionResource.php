<?php

namespace Modules\Speaking\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SpeakingSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'started_at' => $this->started_at,
            'transcripts' => TranscriptResource::collection($this->whenLoaded('transcripts')),
            'created_at' => $this->created_at,
        ];
    }
}
