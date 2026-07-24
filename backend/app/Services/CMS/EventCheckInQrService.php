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

    /**
     * New format: sfumsa:event-checkin:{eventUuid}:{registrationUuid}
     * Legacy format: sfumsa:event-checkin:{registrationUuid}
     */
    public function payloadForRegistration(string $registrationUuid, ?string $eventUuid = null): string
    {
        $registrationUuid = strtolower(trim($registrationUuid));

        if ($eventUuid) {
            return self::PAYLOAD_PREFIX.strtolower(trim($eventUuid)).':'.$registrationUuid;
        }

        return self::PAYLOAD_PREFIX.$registrationUuid;
    }

    /**
     * @return array{eventUuid: ?string, registrationUuid: string}|null
     */
    public function parsePayload(?string $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $value = trim($raw);

        if (str_starts_with($value, self::PAYLOAD_PREFIX)) {
            $value = substr($value, strlen(self::PAYLOAD_PREFIX));
        }

        $value = trim($value);

        // New format: eventUuid:registrationUuid
        if (preg_match(
            '/^([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}):([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$/i',
            $value,
            $matches
        )) {
            return [
                'eventUuid' => strtolower($matches[1]),
                'registrationUuid' => strtolower($matches[2]),
            ];
        }

        // Legacy format: registrationUuid only
        if (preg_match(
            '/^([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$/i',
            $value,
            $matches
        )) {
            return [
                'eventUuid' => null,
                'registrationUuid' => strtolower($matches[1]),
            ];
        }

        return null;
    }

    public function parseRegistrationUuid(?string $raw): ?string
    {
        return $this->parsePayload($raw)['registrationUuid'] ?? null;
    }

    public function parseEventUuid(?string $raw): ?string
    {
        return $this->parsePayload($raw)['eventUuid'] ?? null;
    }

    public function pngBinary(string $registrationUuid, ?string $eventUuid = null): string
    {
        $payload = $this->payloadForRegistration($registrationUuid, $eventUuid);

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

        if (is_string($png) && strlen($png) > 100 && str_starts_with($png, "\x89PNG")) {
            return $png;
        }

        throw new \RuntimeException('Unable to generate a PNG QR code for this registration.');
    }

    public function svgBinary(string $registrationUuid, ?string $eventUuid = null): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $this->payloadForRegistration($registrationUuid, $eventUuid),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 12,
        );

        return $builder->build()->getString();
    }

    public function dataUri(string $registrationUuid, ?string $eventUuid = null): string
    {
        return 'data:image/png;base64,'.base64_encode($this->pngBinary($registrationUuid, $eventUuid));
    }
}
