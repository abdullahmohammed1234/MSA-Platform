<?php

namespace Tests\Support;

use App\Ems\Models\Ticket;
use App\Ems\Services\Ticketing\QrCodeGenerator;

/**
 * Deterministic PNG output so tests never depend on the GD extension of the
 * machine running them. Real GD output is covered by QrCodeGeneratorTest.
 */
class FakeQrCodeGenerator extends QrCodeGenerator
{
    /** 1x1 transparent PNG. */
    public const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89"
        ."\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82";

    public function pngSupported(): bool
    {
        return true;
    }

    public function png(Ticket $ticket, int $size = 280): string
    {
        $this->payloadFor($ticket);

        return self::PNG;
    }
}
