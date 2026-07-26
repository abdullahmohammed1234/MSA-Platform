<?php

namespace App\Ems\Events;

use App\Ems\Models\AttendeeImport;

class AttendeesImported extends EmsDomainEvent
{
    public function action(): string
    {
        return 'attendees.imported';
    }

    public function description(): string
    {
        /** @var AttendeeImport $import */
        $import = $this->subject;
        $imported = (int) ($import->summary['imported'] ?? 0);

        return sprintf('Imported %d attendees from %s.', $imported, $import->original_filename);
    }

    public function payload(): array
    {
        /** @var AttendeeImport $import */
        $import = $this->subject;

        return array_merge($this->context, [
            'import_uuid' => $import->uuid,
            'event_id' => $import->event_id,
            'filename' => $import->original_filename,
            'summary' => $import->summary,
        ]);
    }
}
