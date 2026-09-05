<?php

namespace App\Mlibms\Mail;

use App\Mlibms\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanCheckoutConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SFU MSA Library — Borrowing Confirmation: {$this->loan->copy->book->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mlibms.checkout_confirmation',
        );
    }
}
