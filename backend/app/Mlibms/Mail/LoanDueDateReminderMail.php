<?php

namespace App\Mlibms\Mail;

use App\Mlibms\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanDueDateReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan
    ) {}

    public function envelope(): Envelope
    {
        $dueDateStr = $this->loan->due_at->format('M j, Y');
        return new Envelope(
            subject: "Reminder: '{$this->loan->copy->book->title}' is due in 2 days ({$dueDateStr})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mlibms.due_date_reminder',
        );
    }
}
