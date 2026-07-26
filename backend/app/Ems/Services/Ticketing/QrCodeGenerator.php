<?php

namespace App\Ems\Services\Ticketing;

use App\Ems\Models\Ticket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Server-side QR generation for EMS tickets.
 *
 * The QR encodes only the ticket code (or a validation URL derived from it) —
 * never attendee PII. Binary images are generated on demand rather than stored.
 */
class QrCodeGenerator
{
    /**
     * Build the payload string embedded in the QR code.
     *
     * Prefers a public validation URL when configured so Phase 4 scanners can
     * hit a stable endpoint; falls back to the bare ticket code.
     */
    public function payloadFor(Ticket $ticket): string
    {
        $base = rtrim((string) config('ems.public.ticket_validation_url', ''), '/');

        if ($base !== '') {
            return $base . '/' . $ticket->code;
        }

        $frontend = rtrim((string) config('ems.public.frontend_url', ''), '/');

        if ($frontend !== '') {
            return $frontend . '/tickets/' . $ticket->code;
        }

        return $ticket->code;
    }

    /**
     * PNG binary suitable for an image response or data URI.
     */
    public function png(Ticket $ticket, int $size = 280): string
    {
        if (extension_loaded('gd')) {
            try {
                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $this->payloadFor($ticket),
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: $size,
                    margin: 8,
                );

                return $builder->build()->getString();
            } catch (\Throwable) {
                // Fall through to remote PNG generation.
            }
        }

        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&margin=8&data=' . urlencode($this->payloadFor($ticket));
        $png = @file_get_contents($url);

        if (is_string($png) && strlen($png) > 100 && str_starts_with($png, "\x89PNG")) {
            return $png;
        }

        throw new \RuntimeException('Unable to generate a PNG QR code for this ticket.');
    }

    /**
     * SVG markup for embedding on the ticket page.
     */
    public function svg(Ticket $ticket, int $size = 280): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $this->payloadFor($ticket),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        );

        return $builder->build()->getString();
    }

    /**
     * Data URI for inline <img> tags without a second request.
     */
    public function dataUri(Ticket $ticket, int $size = 280): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($ticket, $size));
    }
}
