<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendeeExportController extends EmsController
{
    public function exportCsv(Request $request, Event $event): StreamedResponse|ApiResponse
    {
        $this->authorize('viewAttendees', $event);

        $filename = 'attendees-' . $event->slug . '-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        $callback = function () use ($event) {
            $handle = fopen('php://output', 'w');

            // Write CSV BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write Headers
            fputcsv($handle, [
                'Registration Number',
                'Attendee Name',
                'Attendee Email',
                'Phone',
                'Status',
                'Quantity',
                'Ticket Codes',
                'Checked In',
                'Registration Date',
            ]);

            Registration::with(['tickets'])
                ->where('event_id', $event->id)
                ->orderBy('created_at', 'asc')
                ->chunk(100, function ($registrations) use ($handle) {
                    foreach ($registrations as $reg) {
                        $ticketCodes = $reg->tickets->pluck('code')->implode(', ');
                        $checkedIn = $reg->tickets->whereNotNull('checked_in_at')->count() > 0 ? 'Yes' : 'No';

                        fputcsv($handle, [
                            $reg->registration_number,
                            $reg->attendee_name,
                            $reg->attendee_email,
                            $reg->attendee_phone ?? 'N/A',
                            strtoupper($reg->status instanceof \BackedEnum ? $reg->status->value : (string) $reg->status),
                            $reg->quantity,
                            $ticketCodes ?: 'N/A',
                            $checkedIn,
                            $reg->created_at?->copy()->setTimezone($event->timezone ?? config('ems.default_timezone', 'America/Vancouver'))->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
