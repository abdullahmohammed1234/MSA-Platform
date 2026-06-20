<?php

namespace Tests\Feature;

use App\Mail\ContactFormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebsiteContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_sends_email_to_configured_recipient(): void
    {
        Mail::fake();
        config(['website.contact_recipient' => 'abdullahelboraei@gmail.com']);

        $payload = [
            'name' => 'Test User',
            'email' => 'visitor@example.com',
            'subject' => 'General inquiry',
            'message' => 'Assalamu alaikum, I have a question about events.',
        ];

        $this->postJson(route('api.website.contact'), $payload)
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(ContactFormSubmission::class, function (ContactFormSubmission $mail) use ($payload) {
            return $mail->hasTo('abdullahelboraei@gmail.com')
                && $mail->senderName === $payload['name']
                && $mail->senderEmail === $payload['email']
                && $mail->subjectLine === $payload['subject']
                && $mail->messageBody === $payload['message'];
        });
    }

    public function test_contact_form_requires_valid_payload(): void
    {
        Mail::fake();

        $this->postJson(route('api.website.contact'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

        Mail::assertNothingSent();
    }
}
