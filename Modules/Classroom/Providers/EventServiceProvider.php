<?php

namespace Modules\Classroom\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Classroom\Events\ClassroomCommentCreated;
use Modules\Classroom\Events\ClassroomPostCreated;
use Modules\Classroom\Listeners\SendClassroomCommentNotifications;
use Modules\Classroom\Listeners\SendClassroomPostNotifications;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ClassroomPostCreated::class => [
            SendClassroomPostNotifications::class,
        ],
        ClassroomCommentCreated::class => [
            SendClassroomCommentNotifications::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
