<?php

namespace App\Services\CMS;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class EventCheckInQrService
{
    public const PAYLOAD_PREFIX = 'sfumsa:event-checkin:';

    public function payloadForRegistration(string $registrationUuid): string
    {
        return self::PAYLOAD_PREFIX.$registrationUuid;
    }

    public function parseRegistrationUuid(?string $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $value = trim($raw);

        if (str_starts_with($value, self::PAYLOAD_PREFIX)) {
            $value = substr($value, strlen(self::PAYLOAD_PREFIX));
        }

        $value = trim($value);

        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) ? strtolower($value) : null;
    }

    public function pngBinary(string $registrationUuid): string
    {
        $payload = $this->payloadForRegistration($registrationUuid);

        if (extension_loaded('gd')) {
            try {
                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $payload,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: 360,
                    margin: 12,
                );

                return $builder->build()->getString();
            } catch (\Throwable) {
                // Fall through to remote PNG generation.
            }
        }

        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data='.urlencode($payload);
        $png = @file_get_contents($url);

        if (is_string($png) && strlen($png) > 100) {
            return $png;
        }

        // Last resort: SVG bytes labeled as image/svg+xml by callers that need a visual.
        return $this->svgBinary($registrationUuid);
    }

    public function svgBinary(string $registrationUuid): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $this->payloadForRegistration($registrationUuid),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 12,
        );

        return $builder->build()->getString();
    }

    public function dataUri(string $registrationUuid): string
    {
        $binary = $this->pngBinary($registrationUuid);

        if (str_starts_with(ltrim($binary), '<svg') || str_starts_with(ltrim($binary), '<?xml')) {
            return 'data:image/svg+xml;base64,'.base64_encode($binary);
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    public function attachmentMime(string $registrationUuid): string
    {
        $binary = $this->pngBinary($registrationUuid);

        if (str_starts_with(ltrim($binary), '<svg') || str_starts_with(ltrim($binary), '<?xml')) {
            return 'image/svg+xml';
        }

        return 'image/png';
    }

    public function attachmentFilename(string $registrationUuid): string
    {
        return $this->attachmentMime($registrationUuid) === 'image/svg+xml'
            ? 'event-checkin-qr.svg'
            : 'event-checkin-qr.png';
    }
}
