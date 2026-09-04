<?php

namespace Tests\Feature;

use App\Mail\ContactFormSubmission;
use App\Mail\VolunteerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebsiteContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_sends_email_to_configured_recipient(): void
    {
        Mail::fake();
        config(['website.contact_recipient' => 'sfumsa@hotmail.com']);

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
            return $mail->hasTo('sfumsa@hotmail.com')
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

    public function test_volunteer_form_sends_email_to_configured_recipient(): void
    {
        Mail::fake();
        config(['website.contact_recipient' => 'sfumsa@hotmail.com']);

        $payload = [
            'name' => 'Test Volunteer',
            'email' => 'volunteer@sfu.ca',
            'student_number' => '301234567',
            'department' => 'Events',
            'interests' => 'I would love to help coordinate weekly Friday prayers and social events.',
            'experience' => 'I have coordinate high school events before.',
        ];

        $this->postJson(route('api.website.volunteer.submit'), $payload)
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(VolunteerApplication::class, function (VolunteerApplication $mail) use ($payload) {
            return $mail->hasTo('sfumsa@hotmail.com')
                && $mail->name === $payload['name']
                && $mail->email === $payload['email']
                && $mail->studentNumber === $payload['student_number']
                && $mail->department === $payload['department']
                && $mail->interests === $payload['interests']
                && $mail->experience === $payload['experience'];
        });
    }

    public function test_volunteer_form_requires_valid_payload(): void
    {
        Mail::fake();

        // Missing fields
        $this->postJson(route('api.website.volunteer.submit'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'student_number', 'department', 'interests']);

        // Invalid email (non-sfu) and student number (not 9 digits)
        $invalidPayload = [
            'name' => 'Test Volunteer',
            'email' => 'volunteer@gmail.com',
            'student_number' => '12345',
            'department' => 'Events',
            'interests' => 'Help out',
        ];

        $this->postJson(route('api.website.volunteer.submit'), $invalidPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'student_number']);

        Mail::assertNothingSent();
    }
}
