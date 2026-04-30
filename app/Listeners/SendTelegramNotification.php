<?php

namespace App\Listeners;

use App\Events\StudentRegistered;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramNotification implements ShouldQueue
{
    /**
     * Queue connection: dùng 'sync' nếu không có worker, 'database' nếu muốn non-blocking.
     */
    public string $connection = 'sync';

    public function __construct(private TelegramService $telegram)
    {
    }

    public function handle(StudentRegistered $event): void
    {
        $this->telegram->sendStudentApprovalRequest($event->user);
    }
}
