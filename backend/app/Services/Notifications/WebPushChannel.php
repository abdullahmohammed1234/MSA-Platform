<?php

namespace App\Services\Notifications;

use App\Services\WebPush\WebPushService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(
        protected WebPushService $pushService
    ) {}

    /**
     * Send the given notification via Web Push.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        $this->pushService->deliver($notifiable, $notification);
    }
}
