<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Services\Ticketing\DefaultTicketIssuer;
use App\Ems\Services\Ticketing\QrCodeGenerator;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_unique_codes_and_qr_payloads(): void
    {
        $registration = Registration::factory()->create([
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 2,
        ]);

        $issuer = app(DefaultTicketIssuer::class);
        $tickets = $issuer->issueFor($registration);

        $this->assertCount(2, $tickets);
        $this->assertNotSame($tickets[0]->code, $tickets[1]->code);
        $this->assertNotEmpty($tickets[0]->qr_payload);
        $this->assertNotNull($tickets[0]->qr_generated_at);

        // Idempotent — second call returns existing tickets.
        $again = $issuer->issueFor($registration->fresh());
        $this->assertCount(2, $again);
        $this->assertSame(2, Ticket::where('registration_id', $registration->id)->count());
    }

    public function test_code_generator_format(): void
    {
        config(['ems.tickets.code_prefix' => 'MSA', 'ems.tickets.code_length' => 10]);

        $code = app(TicketCodeGenerator::class)->generate();

        $this->assertMatchesRegularExpression('/^MSA-[0-9A-HJKMNP-TV-Z]{10}$/', $code);
    }

    public function test_qr_payload_prefers_validation_url(): void
    {
        config([
            'ems.public.ticket_validation_url' => 'https://example.com/validate',
            'ems.public.frontend_url' => 'https://frontend.example',
        ]);

        $ticket = Ticket::factory()->make(['code' => 'MSA-ABCDEF1234']);
        $payload = app(QrCodeGenerator::class)->payloadFor($ticket);

        $this->assertSame('https://example.com/validate/MSA-ABCDEF1234', $payload);
    }
}
