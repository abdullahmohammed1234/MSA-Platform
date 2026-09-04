<?php

namespace App\Console\Commands;

use App\Mail\DailyVolunteerDigestMail;
use App\Models\VolunteerRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyVolunteerDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'volunteer:send-daily-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an end-of-day alert digest email to sfumsa@hotmail.com if new volunteer registrations were submitted today.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $todayStr = now()->toDateString();
        
        $registrations = VolunteerRegistration::whereDate('created_at', $todayStr)
            ->latest()
            ->get();

        $count = $registrations->count();

        if ($count === 0) {
            $this->info("No new volunteer registrations submitted today ({$todayStr}). Skipping digest email.");
            return Command::SUCCESS;
        }

        $recipient = config('website.contact_recipient', 'sfumsa@hotmail.com');
        if (empty($recipient)) {
            $recipient = 'sfumsa@hotmail.com';
        }

        try {
            Mail::to($recipient)->send(new DailyVolunteerDigestMail($registrations, $todayStr));

            $this->info("Successfully sent daily volunteer digest email ({$count} applications) to {$recipient}.");
            Log::info("Sent daily volunteer digest email to {$recipient}", [
                'count' => $count,
                'date' => $todayStr,
            ]);
        } catch (Throwable $e) {
            $this->error("Failed to send daily volunteer digest email: " . $e->getMessage());
            Log::error("Failed to send daily volunteer digest email", [
                'error' => $e->getMessage(),
                'date' => $todayStr,
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
