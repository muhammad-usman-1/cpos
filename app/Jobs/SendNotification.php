<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one logged message. A failed send is retried twice, a few minutes apart.
 */
class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationLogId) {}

    public function handle(NotificationService $notifications): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);

        if (! $log || $log->status === NotificationLog::SENT) {
            return;
        }

        $result = $notifications->deliver($log);

        if (! $result->delivered && ! $result->loggedOnly && $this->attempts() < $this->tries) {
            $this->release(120 * $this->attempts());
        }
    }
}
