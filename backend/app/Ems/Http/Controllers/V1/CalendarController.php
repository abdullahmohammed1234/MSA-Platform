<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarController extends EmsController
{
    /**
     * GET /api/v1/ems/public/events/{slug}/calendar
     * Returns Google, Outlook, Yahoo web calendar compose links.
     */
    public function links(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $start = $event->start_at->utc()->format('Ymd\THis\Z');
        $end = ($event->end_at ?? $event->start_at->addHours(2))->utc()->format('Ymd\THis\Z');

        $googleUrl = "https://calendar.google.com/calendar/render?action=TEMPLATE&text=" 
            . urlencode($event->name) . "&dates={$start}/{$end}&details=" 
            . urlencode($event->description ?? '') . "&location=" . urlencode($event->location ?? '');

        $outlookUrl = "https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent&subject=" 
            . urlencode($event->name) . "&startdt=" . urlencode($event->start_at->toIso8601String()) . "&enddt=" 
            . urlencode(($event->end_at ?? $event->start_at->addHours(2))->toIso8601String()) . "&body=" 
            . urlencode($event->description ?? '') . "&location=" . urlencode($event->location ?? '');

        $yahooUrl = "https://calendar.yahoo.com/?v=60&view=d&type=20&title=" 
            . urlencode($event->name) . "&st={$start}&et={$end}&desc=" 
            . urlencode($event->description ?? '') . "&in_loc=" . urlencode($event->location ?? '');

        return ApiResponse::success([
            'google' => $googleUrl,
            'outlook' => $outlookUrl,
            'yahoo' => $yahooUrl,
            'ics' => route('api.ems.public.events.ics', ['slug' => $event->slug]),
        ], 'Calendar links generated.');
    }

    /**
     * GET /api/v1/ems/public/events/{slug}/ics
     * Serves dynamic .ics file download.
     */
    public function ics(string $slug): Response
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MSA Dawah//EMS//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $event->uuid . "\r\n";
        $ics .= "DTSTAMP:" . now()->utc()->format('Ymd\THis\Z') . "\r\n";
        $ics .= "DTSTART:" . $event->start_at->utc()->format('Ymd\THis\Z') . "\r\n";
        $ics .= "DTEND:" . ($event->end_at ?? $event->start_at->addHours(2))->utc()->format('Ymd\THis\Z') . "\r\n";
        $ics .= "SUMMARY:" . $event->name . "\r\n";
        $ics .= "DESCRIPTION:" . str_replace("\n", "\\n", $event->description ?? '') . "\r\n";
        $ics .= "LOCATION:" . ($event->location ?? '') . "\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($event->name) . '.ics"',
        ]);
    }
}
