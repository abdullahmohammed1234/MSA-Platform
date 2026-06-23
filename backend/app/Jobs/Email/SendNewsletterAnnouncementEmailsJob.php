<?php

namespace App\Jobs\Email;

use App\Mail\NewsletterAnnouncement;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletterAnnouncementEmailsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        protected string $announcementTitle,
        protected string $announcementExcerpt,
        protected string $announcementSlug,
    ) {
    }

    public function handle(): void
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $announcementUrl = $frontendUrl.'/';

        NewsletterSubscriber::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($subscribers) use ($announcementUrl) {
                foreach ($subscribers as $subscriber) {
                    try {
                        Mail::to($subscriber->email)->send(new NewsletterAnnouncement(
                            $subscriber->email,
                            $this->announcementTitle,
                            $this->announcementExcerpt,
                            $announcementUrl,
                        ));
                    } catch (Throwable $exception) {
                        Log::error('Failed to send newsletter announcement email', [
                            'email' => $subscriber->email,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Newsletter announcement batch job failed: '.$exception->getMessage());
    }
}
