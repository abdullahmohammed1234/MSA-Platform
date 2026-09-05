<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mlibms\Mail\LoanDueDateReminderMail;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MlibmsNotificationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_day_due_reminder_is_dispatched_and_idempotent(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $book = Book::create(['title' => 'The Sealed Nectar', 'slug' => 'the-sealed-nectar']);
        $copy = Copy::create(['book_id' => $book->id, 'barcode' => 'MLIB-C-999', 'accession_number' => 'MLIB-A-999', 'status' => 'checked_out']);
        $member = Member::create(['user_id' => $user->id, 'library_card_number' => 'CARD-999', 'name' => $user->name, 'email' => $user->email]);

        // Loan due in exactly 2 calendar days
        $loan = Loan::create([
            'copy_id' => $copy->id,
            'member_id' => $member->id,
            'checked_out_at' => now()->subDays(12),
            'due_at' => now()->addDays(2),
            'status' => 'active',
            'reminder_sent_at' => null,
        ]);

        // Run scheduled command first time
        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        Mail::assertQueued(LoanDueDateReminderMail::class, 1);
        $loan->refresh();
        $this->assertNotNull($loan->reminder_sent_at);

        // Run scheduled command second time -> Zero additional mail queued
        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);
        Mail::assertQueued(LoanDueDateReminderMail::class, 1);
    }
}
