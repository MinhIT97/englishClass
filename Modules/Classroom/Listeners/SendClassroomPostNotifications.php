<?php

namespace Modules\Classroom\Listeners;

use App\Events\NewPostPublished;
use App\Notifications\ClassroomNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Modules\Classroom\Events\ClassroomPostCreated;

class SendClassroomPostNotifications implements ShouldQueue
{
    public function handle(ClassroomPostCreated $event): void
    {
        $post = $event->post->loadMissing(['classroom.teacher', 'classroom.students', 'user']);

        broadcast(new NewPostPublished($post))->toOthers();

        $members = $post->classroom->students
            ->concat([$post->classroom->teacher])
            ->filter(fn ($member) => $member && $member->id !== $post->user_id)
            ->unique('id');

        if ($members->isEmpty()) {
            return;
        }

        Notification::send(
            $members,
            new ClassroomNotification(
                $post->classroom->name,
                $post->user->name . ' posted a new ' . $post->type,
                route('classroom.show', $post->classroom_id)
            )
        );
    }
}
