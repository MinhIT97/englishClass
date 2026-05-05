<?php

namespace Modules\Classroom\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\ClassroomComment;

class ClassroomCommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClassroomComment $comment)
    {
    }
}
