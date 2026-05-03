<?php

namespace Modules\Classroom\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'user_name' => $this->user?->name,
            'user_initial' => strtoupper(substr($this->user?->name ?? '?', 0, 1)),
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }
}
