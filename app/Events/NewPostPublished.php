<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPostPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $post;

    /**
     * Create a new event instance.
     */
    public function __construct(\Modules\Classroom\Models\ClassroomPost $post)
    {
        $this->post = $post;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('classroom.' . $this->post->classroom_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NewPostPublished';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->id,
            'content' => $this->post->content,
            'type' => $this->post->type,
            'attachment_path' => $this->post->attachment_path,
            'attachment_url' => $this->post->attachment_path ? asset('storage/' . $this->post->attachment_path) : null,
            'user_id' => $this->post->user_id,
            'user_name' => $this->post->user->name,
            'user_role' => $this->post->user->role,
            'user_initial' => substr($this->post->user->name, 0, 1),
            'classroom_id' => $this->post->classroom_id,
            'created_at' => $this->post->created_at->diffForHumans(),
        ];
    }
}
