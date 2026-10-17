<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Services\Ticketing\QrCodeGenerator;
use App\Ems\Services\Ticketing\QrImageUnavailableException;
use Illuminate\Support\Str;

class QrCodeGeneratorTest extends EmsTestCase
{
    private function ticket(): Ticket
    {
        $event = \App\Ems\Models\Event::factory()->publiclyDiscoverable()->create([
            'category_id' => $this->category(['is_active' => true])->id,
            'slug' => 'qr-event-'.Str::lower(Str::random(4)),
        ]);
        $registration = Registration::factory()->create(['event_id' => $event->id]);

        return Ticket::factory()->forRegistration($registration)->create([
            'code' => 'MSA-QRTEST0001',
            'qr_payload' => 'MSA-QRTEST0001',
        ]);
    }

    private function useGeneratorWithoutGd(): void
    {
        $this->app->instance(QrCodeGenerator::class, new class extends QrCodeGenerator {
            public function pngSupported(): bool
            {
                return false;
            }
        });
    }

    public function test_generator_has_no_third_party_fallback(): void
    {
        $source = file_get_contents(app_path('Ems/Services/Ticketing/QrCodeGenerator.php'));

        $this->assertDoesNotMatchRegularExpression('#https?://#', $source);
        $this->assertStringNotContainsString('file_get_contents', $source);
    }

    public function test_without_gd_png_is_unavailable_instead_of_remote(): void
    {
        $this->useGeneratorWithoutGd();
        $ticket = $this->ticket();
        $generator = app(QrCodeGenerator::class);

        $this->assertNull($generator->dataUri($ticket));
        $this->expectException(QrImageUnavailableException::class);
        $generator->png($ticket);
    }

    public function test_without_gd_ticket_api_omits_image_and_qr_endpoint_returns_503(): void
    {
        $this->useGeneratorWithoutGd();
        $this->ticket();

        $show = $this->getJson($this->url('public/tickets/MSA-QRTEST0001'))->assertOk();
        $show->assertJsonPath('data.code', 'MSA-QRTEST0001');
        $show->assertJsonPath('data.qr_payload', 'MSA-QRTEST0001');
        $this->assertNull($show->json('data.qr_image'));

        $this->get($this->url('public/tickets/MSA-QRTEST0001/qr'))->assertStatus(503);
    }

    public function test_real_gd_generation_produces_png(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not loaded on this PHP build.');
        }

        $png = (new QrCodeGenerator())->png($this->ticket());

        $this->assertStringStartsWith("\x89PNG", $png);
    }
}
