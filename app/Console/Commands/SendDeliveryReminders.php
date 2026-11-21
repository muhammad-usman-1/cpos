<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\StitchingOrder;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reminds customers to collect ready orders (NTF01). Runs daily from the scheduler; each order is reminded
 * at most once per reminder period.
 */
#[Signature('orders:send-collection-reminders')]
#[Description('Message customers whose stitching orders are ready but not collected')]
class SendDeliveryReminders extends Command
{
    public function handle(NotificationService $notifications): int
    {
        if (! Setting::usesMessaging() || ! Setting::enabled(NotificationEvent::DeliveryReminder->settingKey())) {
            $this->info('Collection reminders are switched off in settings.');

            return self::SUCCESS;
        }

        $days = max(1, (int) Setting::get('reminder_after_days'));
        $sent = 0;

        $orders = StitchingOrder::query()
            ->with('customer')
            ->where('status', OrderStatus::Ready)
            ->where('ready_at', '<=', now()->subDays($days))
            ->get();

        foreach ($orders as $order) {
            $recentlyReminded = NotificationLog::query()
                ->where('event', NotificationEvent::DeliveryReminder)
                ->where('related_type', $order->getMorphClass())
                ->where('related_id', $order->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->exists();

            if (! $recentlyReminded && $notifications->notifyDeliveryReminder($order)) {
                $sent++;
            }
        }

        $this->info("Queued {$sent} collection reminders.");

        return self::SUCCESS;
    }
}
