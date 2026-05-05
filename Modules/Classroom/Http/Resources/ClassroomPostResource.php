<?php

namespace Modules\Classroom\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_path ? asset('storage/' . $this->attachment_path) : null,
            'user_name' => $this->user?->name,
            'user_role' => $this->user?->role,
            'user_initial' => strtoupper(substr($this->user?->name ?? '?', 0, 1)),
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }
}
