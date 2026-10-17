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
     *
     * Generated locally only: the payload is a check-in credential and must
     * never be sent to a third-party QR service.
     *
     * @throws QrImageUnavailableException when the GD extension is missing
     */
    public function png(Ticket $ticket, int $size = 280): string
    {
        if (! $this->pngSupported()) {
            throw new QrImageUnavailableException('PNG QR generation requires the GD extension.');
        }

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
        } catch (\Throwable $e) {
            throw new QrImageUnavailableException('Unable to generate a PNG QR code for this ticket.', 0, $e);
        }
    }

    public function pngSupported(): bool
    {
        return extension_loaded('gd');
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
     * PNG data URI for inline <img> tags, or null when PNG generation is
     * unavailable. Clients accept only PNG data URIs and hide a missing image.
     */
    public function dataUri(Ticket $ticket, int $size = 280): ?string
    {
        try {
            return 'data:image/png;base64,' . base64_encode($this->png($ticket, $size));
        } catch (QrImageUnavailableException) {
            return null;
        }
    }
}
