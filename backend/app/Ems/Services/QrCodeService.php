<?php

namespace App\Ems\Services;

use App\Ems\Models\Event;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    public function canonicalEventUrl(Event $event): string
    {
        $frontendUrl = rtrim((string) config('app.url', config('ems.public.frontend_url', 'http://localhost:3000')), '/');
        return $frontendUrl . '/events/' . $event->slug;
    }

    public function generateEventQrDataUri(Event $event, int $size = 280): string
    {
        $url = $this->canonicalEventUrl($event);
        return $this->generateDataUriForUrl($url, $size);
    }

    public function generateDataUriForUrl(string $url, int $size = 280): string
    {
        if (extension_loaded('gd')) {
            try {
                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $url,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: $size,
                    margin: 8,
                );

                return 'data:image/png;base64,' . base64_encode($builder->build()->getString());
            } catch (\Throwable) {
                // Fall back to QR server if GD fails
            }
        }

        $qrServerUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&margin=8&data=' . urlencode($url);
        $png = @file_get_contents($qrServerUrl);

        if (is_string($png) && strlen($png) > 100 && str_starts_with($png, "\x89PNG")) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        return 'data:image/svg+xml;utf8,' . rawurlencode($this->generateSvgForUrl($url, $size));
    }

    public function generateSvgForUrl(string $url, int $size = 280): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        );

        return $builder->build()->getString();
    }
}
