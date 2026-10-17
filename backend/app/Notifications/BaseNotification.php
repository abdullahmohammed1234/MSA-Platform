<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Base notification for platform-level asynchronous notifications that require a background queue worker.
 */
abstract class BaseNotification extends ImmediateNotification implements ShouldQueue
{
    use Queueable;
}
