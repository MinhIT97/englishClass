<?php

namespace Modules\Speaking\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'feedback' => $this->feedback,
            'created_at' => $this->created_at,
        ];
    }
}
