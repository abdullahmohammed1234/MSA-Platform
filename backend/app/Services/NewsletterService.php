<?php

namespace App\Services;

use App\Jobs\Email\SendNewsletterAnnouncementEmailsJob;
use App\Mail\NewsletterWelcome;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class NewsletterService
{
    public function subscribe(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $existing = NewsletterSubscriber::where('email', $normalizedEmail)->first();

        if ($existing) {
            if (!$existing->is_active) {
                $existing->update([
                    'is_active' => true,
                    'subscribed_at' => now(),
                ]);
                $this->sendWelcomeEmail($existing->email);

                return [
                    'created' => true,
                    'message' => 'Welcome back! You have been resubscribed to our newsletter.',
                ];
            }

            return [
                'created' => false,
                'message' => 'You are already subscribed to our newsletter.',
            ];
        }

        NewsletterSubscriber::create([
            'uuid' => (string) Str::uuid(),
            'email' => $normalizedEmail,
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        $this->sendWelcomeEmail($normalizedEmail);

        return [
            'created' => true,
            'message' => 'Thank you for subscribing! Check your inbox for a welcome email.',
        ];
    }

    public function sendAnnouncementToSubscribers(string $title, string $excerpt, string $slug): void
    {
        $subscriberCount = NewsletterSubscriber::query()
            ->where('is_active', true)
            ->count();

        if ($subscriberCount === 0) {
            return;
        }

        SendNewsletterAnnouncementEmailsJob::dispatch($title, $excerpt, $slug);
    }

    protected function sendWelcomeEmail(string $email): void
    {
        try {
            Mail::to($email)->queue(new NewsletterWelcome($email));
        } catch (Throwable $exception) {
            Log::error('Newsletter welcome email failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
