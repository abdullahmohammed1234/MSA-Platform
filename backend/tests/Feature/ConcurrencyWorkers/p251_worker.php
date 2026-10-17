<?php

/**
 * Phase 25.1 multi-process concurrency worker (MySQL/MariaDB *_test databases only).
 *
 * Usage: php p251_worker.php <barrier_file> <action> [args...]
 *
 * Boots the app, signals readiness with "<barrier>.ready.<pid>", spins until the
 * barrier file disappears, runs one production code path and prints JSON.
 * External services are faked in-process; nothing leaves the machine.
 */

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Ems\Jobs\ProcessDueNotificationsJob;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Payment;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\SquareWebhookService;
use App\Events\AnnouncementPublishedEvent;
use App\Mail\DailyVolunteerDigestMail;
use App\Mlibms\Mail\LoanDueDateReminderMail;
use App\Mlibms\Mail\LoanOverdueNoticeMail;
use App\Models\CMS\Announcement;
use App\Models\NotificationLog;
use App\Services\CMS\AnnouncementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

$barrier = $argv[1] ?? null;
$action = $argv[2] ?? null;
$args = array_slice($argv, 3);

$connection = config('database.default');
$database = (string) config("database.connections.{$connection}.database");
if ($connection !== 'mysql' || ! str_ends_with($database, '_test')) {
    echo json_encode(['error' => "Safety guard: refusing {$connection}/{$database}"]);
    exit(1);
}

config([
    'ems.payments.enabled' => true,
    'ems.payments.square.access_token' => 'test-token',
    'ems.payments.square.location_id' => 'LOCATION_TEST',
    'ems.payments.square.webhook_signature_key' => 'webhook-secret',
    'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
    'ems.payments.square.environment' => 'sandbox',
    'ems.notifications.enabled' => true,
    'features.mlibms_overdue_processing_enabled' => true,
    'features.volunteer_digest_enabled' => true,
    'mail.default' => 'array',
]);

$pid = getmypid();
Http::fake(function ($request) use ($pid) {
    if (str_contains($request->url(), '/v2/online-checkout/payment-links') && $request->method() === 'POST') {
        $id = $pid.'_'.bin2hex(random_bytes(4));

        return Http::response(['payment_link' => ['id' => "plink_{$id}", 'url' => "https://square.test/{$id}", 'order_id' => "sq_order_{$id}"]], 200);
    }

    return Http::response(['objects' => [], 'id_mappings' => []], 200);
});

DB::selectOne('select 1');
$connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;

if ($barrier) {
    touch($barrier.'.ready.'.$pid);
    $start = microtime(true);
    while (file_exists($barrier) && (microtime(true) - $start) < 20.0) {
        usleep(500);
    }
}

$result = ['pid' => $pid, 'connection_id' => $connectionId, 'action' => $action];

try {
    switch ($action) {
        case 'checkout':
            [$eventId, $email] = $args;
            $ticketTypeId = TicketType::query()->where('event_id', $eventId)->value('uuid');
            $out = app(CheckoutService::class)->checkout(Event::query()->findOrFail($eventId), [
                'first_name' => 'Race', 'last_name' => 'Buyer', 'email' => $email,
                'ticket_type_id' => $ticketTypeId, 'quantity' => 1,
            ]);
            $result['ok'] = true;
            $result['payment_id'] = $out['payment']?->id;
            break;

        case 'mark_paid':
            [$paymentId, $squarePaymentId] = $args;
            $payment = app(PaymentFulfillmentService::class)->markPaid(Payment::query()->findOrFail($paymentId), ['provider_payment_id' => $squarePaymentId]);
            $result['ok'] = true;
            $result['status'] = $payment->status->value;
            break;

        case 'webhook_claim':
            $result['claimed'] = WebhookEvent::claimForProcessing((int) $args[0]);
            break;

        case 'webhook_process':
            app(SquareWebhookService::class)->processRecord(WebhookEvent::query()->findOrFail((int) $args[0]));
            $result['ok'] = true;
            break;

        case 'webhook_handle':
            [$bodyFile, $signature] = $args;
            app(SquareWebhookService::class)->handle((string) file_get_contents($bodyFile), $signature, 'https://example.test/api/v1/webhooks/square');
            $result['ok'] = true;
            break;

        case 'notif_claim':
            $result['claimed'] = EventNotification::claimForDispatch((int) $args[0]);
            break;

        case 'ems_retry':
            app(QueuedEventNotificationDispatcher::class)->retry(EventNotification::query()->findOrFail((int) $args[0]));
            $result['ok'] = true;
            break;

        case 'ems_sweep':
            (new ProcessDueNotificationsJob())->handle(app(QueuedEventNotificationDispatcher::class));
            $result['ok'] = true;
            break;

        case 'mlibms':
            Mail::fake();
            Artisan::call('mlibms:process-overdue-and-reminders');
            $result['reminders'] = Mail::queued(LoanDueDateReminderMail::class)->count();
            $result['overdue'] = Mail::queued(LoanOverdueNoticeMail::class)->count();
            break;

        case 'digest':
            Mail::fake();
            Artisan::call('volunteer:send-daily-digest');
            $result['digests'] = Mail::sent(DailyVolunteerDigestMail::class)->count();
            break;

        case 'resend_claim':
            $result['claimed'] = NotificationLog::claimForResend((int) $args[0]);
            break;

        case 'announce_send':
            app(\App\Services\Announcements\AnnouncementDeliveryService::class)
                ->sendDue(\App\Models\AnnouncementDispatch::query()->findOrFail((int) $args[0]));
            $recipients = [];
            foreach (app('mailer')->getSymfonyTransport()->messages() as $message) {
                foreach ($message->getEnvelope()->getRecipients() as $address) {
                    $recipients[] = $address->getAddress();
                }
            }
            $result['emails'] = $recipients;
            break;

        case 'webhook_requeue':
            $result['requeued'] = WebhookEvent::requeueForOperator((int) $args[0]);
            break;

        case 'announce_publish':
            EventFacade::fake([AnnouncementPublishedEvent::class]);
            app(AnnouncementService::class)->update(Announcement::query()->findOrFail((int) $args[0]), ['status' => 'published'], null);
            $result['dispatched'] = EventFacade::dispatched(AnnouncementPublishedEvent::class)->count();
            break;

        default:
            $result['error'] = 'unknown action';
            echo json_encode($result);
            exit(1);
    }
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['exception'] = $e::class;
    $result['message'] = mb_substr($e->getMessage(), 0, 200);
}

echo json_encode($result);
exit(0);
