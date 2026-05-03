<?php

namespace Modules\Classroom\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\ClassroomPost;

class ClassroomPostCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClassroomPost $post)
    {
    }
}
