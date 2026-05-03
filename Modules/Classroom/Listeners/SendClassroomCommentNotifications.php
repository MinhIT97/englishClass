<?php

namespace Modules\Classroom\Listeners;

use App\Events\CommentPublished;
use App\Notifications\ClassroomNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Classroom\Events\ClassroomCommentCreated;

class SendClassroomCommentNotifications implements ShouldQueue
{
    public function handle(ClassroomCommentCreated $event): void
    {
        $comment = $event->comment->loadMissing(['user', 'post.user', 'post.classroom']);
        $post = $comment->post;

        broadcast(new CommentPublished($comment))->toOthers();

        if ($post->user_id === $comment->user_id) {
            return;
        }

        $post->user->notify(new ClassroomNotification(
            'New comment on your post',
            $comment->user->name . ' commented on your post in ' . $post->classroom->name,
            route('classroom.show', $post->classroom_id)
        ));
    }
}
