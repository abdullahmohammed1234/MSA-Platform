<?php

namespace App\Ems\Services\Operations;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\AttendeeImportStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Events\AttendeesImported;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Exceptions\EmsException;
use App\Ems\Jobs\ProcessAttendeeImportJob;
use App\Ems\Models\AttendeeImport;
use App\Ems\Models\Event;
use App\Ems\Models\ImportColumnMapping;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Response;

class AttendeeImportService
{
    public const EMS_FIELDS = [
        'name',
        'email',
        'phone',
        'ticket_type',
        'member_status',
        'registration_status',
        'payment_status',
    ];

    public function __construct(
        private readonly TicketCodeGenerator $codes,
        private readonly TicketIssuer $tickets,
    ) {
    }

    /**
     * @param  array<string, string|null>  $mapping  ems_field => spreadsheet_column
     * @return array{import: AttendeeImport, preview: array<string, mixed>}
     */
    public function preview(Event $event, UploadedFile $file, array $mapping, User $user): array
    {
        $rows = $this->readSpreadsheet($file);
        $validated = $this->validateRows($event, $rows, $mapping);

        $import = new AttendeeImport();
        $import->event_id = $event->id;
        $import->imported_by = $user->id;
        $import->original_filename = $file->getClientOriginalName();
        $import->source = 'excel_csv';
        $import->status = AttendeeImportStatus::Previewed;
        $import->column_mapping = $mapping;
        $import->summary = [
            'total' => count($rows),
            'valid' => count($validated['valid']),
            'invalid' => count($validated['invalid']),
            'duplicates' => count($validated['duplicates']),
            'tickets_to_generate' => count($validated['valid']),
            'valid_rows' => $validated['valid'],
            'invalid_rows' => $validated['invalid'],
            'duplicate_rows' => $validated['duplicates'],
            'headers' => $validated['headers'],
        ];
        $import->save();

        // Persist file for the commit job.
        $path = $file->storeAs(
            'ems/imports/' . $event->uuid,
            $import->uuid . '.' . strtolower($file->getClientOriginalExtension() ?: 'csv'),
            config('ems.storage.disk', 'local')
        );
        $summary = $import->summary;
        $summary['stored_path'] = $path;
        $import->summary = $summary;
        $import->save();

        return [
            'import' => $import,
            'preview' => [
                'import_uuid' => $import->uuid,
                'total' => $summary['total'],
                'valid' => $summary['valid'],
                'invalid' => $summary['invalid'],
                'duplicates' => $summary['duplicates'],
                'tickets_to_generate' => $summary['tickets_to_generate'],
                'valid_rows' => array_slice($validated['valid'], 0, 50),
                'invalid_rows' => $validated['invalid'],
                'duplicate_rows' => $validated['duplicates'],
                'headers' => $validated['headers'],
            ],
        ];
    }

    /**
     * @return array{import: AttendeeImport, queued: bool}
     */
    public function commit(Event $event, string $importUuid, User $user): array
    {
        $import = AttendeeImport::query()
            ->where('event_id', $event->id)
            ->where('uuid', $importUuid)
            ->firstOrFail();

        if ($import->status !== AttendeeImportStatus::Previewed
            && $import->status !== AttendeeImportStatus::Failed
        ) {
            throw new EmsException(
                'Import is not ready to commit.',
                ['status' => ['Import must be previewed first.']],
                Response::HTTP_CONFLICT
            );
        }

        $validCount = (int) ($import->summary['valid'] ?? 0);
        $threshold = (int) config('ems.operations.import_sync_threshold', 50);

        $import->status = AttendeeImportStatus::Processing;
        $import->started_at = now();
        $import->imported_by = $user->id;
        $import->save();

        if ($validCount <= $threshold) {
            $this->processImport($import);

            return ['import' => $import->fresh(), 'queued' => false];
        }

        ProcessAttendeeImportJob::dispatch($import->id);

        return ['import' => $import->fresh(), 'queued' => true];
    }

    public function processImport(AttendeeImport $import): AttendeeImport
    {
        $event = $import->event()->firstOrFail();
        $mapping = $import->column_mapping ?? [];
        $validRows = $import->summary['valid_rows'] ?? [];

        if ($validRows === [] && ! empty($import->summary['stored_path'])) {
            $absolute = Storage::disk(config('ems.storage.disk', 'local'))
                ->path($import->summary['stored_path']);
            $rows = $this->readPath($absolute);
            $validated = $this->validateRows($event, $rows, $mapping);
            $validRows = $validated['valid'];
        }

        $imported = 0;
        $failed = [];

        DB::transaction(function () use ($event, $import, $validRows, &$imported, &$failed) {
            foreach ($validRows as $row) {
                try {
                    $this->importRow($event, $import, $row);
                    $imported++;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'row' => $row['row_number'] ?? null,
                        'email' => $row['email'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        $summary = $import->summary ?? [];
        $summary['imported'] = $imported;
        $summary['import_failures'] = $failed;
        $import->summary = $summary;
        $import->status = AttendeeImportStatus::Completed;
        $import->completed_at = now();
        $import->error_message = $failed === [] ? null : 'Some rows failed during import.';
        $import->save();

        AttendeesImported::dispatch($import, $import->importer);

        return $import->fresh();
    }

    /**
     * @param  array<string, string|null>  $mapping
     */
    public function saveMapping(Event $event, User $user, string $name, array $mapping): ImportColumnMapping
    {
        $record = ImportColumnMapping::query()->firstOrNew([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'name' => $name,
        ]);
        $record->mapping = $mapping;
        $record->save();

        return $record;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ImportColumnMapping>
     */
    public function listMappings(Event $event, User $user)
    {
        return ImportColumnMapping::query()
            ->where(function ($q) use ($event, $user) {
                $q->where('user_id', $user->id)
                    ->orWhere('event_id', $event->id);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readSpreadsheet(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');

        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw new EmsException(
                'Unsupported file type. Upload a CSV or Excel (.xlsx) file.',
                ['file' => ['Must be .csv or .xlsx']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->readPath($file->getRealPath(), $ext);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readPath(string $path, ?string $ext = null): array
    {
        $ext = $ext ?? strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->readCsv($path);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($matrix) ?? []);
        $rows = [];

        foreach ($matrix as $index => $cells) {
            if ($this->rowIsEmpty($cells)) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $assoc['__row'] = $index + 2;
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string|null>  $mapping
     * @return array{valid: list<array<string, mixed>>, invalid: list<array<string, mixed>>, duplicates: list<array<string, mixed>>, headers: list<string>}
     */
    public function validateRows(Event $event, array $rows, array $mapping): array
    {
        $headers = $rows === [] ? [] : array_values(array_filter(
            array_keys($rows[0]),
            fn ($k) => $k !== '__row'
        ));

        $ticketTypes = TicketType::query()
            ->where('event_id', $event->id)
            ->get();

        $existingEmails = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Waitlisted->value,
            ])
            ->pluck('attendee_email')
            ->map(fn ($e) => strtolower((string) $e))
            ->all();

        $seenInFile = [];
        $valid = [];
        $invalid = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row, $mapping);
            $rowNumber = (int) ($row['__row'] ?? 0);
            $errors = [];
            $warnings = [];

            $name = trim((string) ($mapped['name'] ?? ''));
            $email = strtolower(trim((string) ($mapped['email'] ?? '')));

            if ($name === '') {
                $errors[] = 'Missing name';
            }

            if ($email === '') {
                $errors[] = 'Missing email';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email';
            }

            $ticketType = $this->resolveTicketType($ticketTypes, $mapped['ticket_type'] ?? null);
            if (($mapped['ticket_type'] ?? '') !== '' && $mapped['ticket_type'] !== null && $ticketType === null) {
                $errors[] = 'Invalid ticket type';
            }

            $regStatus = $this->parseRegistrationStatus($mapped['registration_status'] ?? null);
            if (($mapped['registration_status'] ?? '') !== '' && $mapped['registration_status'] !== null && $regStatus === null) {
                $errors[] = 'Invalid registration status';
            }

            $payStatus = $this->parsePaymentStatus($mapped['payment_status'] ?? null);
            if (($mapped['payment_status'] ?? '') !== '' && $mapped['payment_status'] !== null && $payStatus === null) {
                $errors[] = 'Invalid payment status';
            }

            $payload = [
                'row_number' => $rowNumber,
                'name' => $name,
                'email' => $email,
                'phone' => trim((string) ($mapped['phone'] ?? '')) ?: null,
                'ticket_type' => $mapped['ticket_type'] ?? null,
                'ticket_type_id' => $ticketType?->uuid,
                'ticket_type_db_id' => $ticketType?->id,
                'is_member' => $this->parseMemberStatus($mapped['member_status'] ?? null),
                'registration_status' => $regStatus?->value ?? RegistrationStatus::Confirmed->value,
                'payment_status' => $payStatus?->value ?? PaymentStatus::Paid->value,
                'errors' => $errors,
                'warnings' => $warnings,
            ];

            if ($errors !== []) {
                $invalid[] = $payload;
                continue;
            }

            if (isset($seenInFile[$email]) || in_array($email, $existingEmails, true)) {
                $payload['warnings'][] = isset($seenInFile[$email])
                    ? 'Duplicate email in file'
                    : 'Email already registered for this event';
                $duplicates[] = $payload;
                continue;
            }

            $seenInFile[$email] = true;
            $valid[] = $payload;
        }

        return compact('valid', 'invalid', 'duplicates', 'headers');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(Event $event, AttendeeImport $import, array $row): Registration
    {
        $ticketTypeId = $row['ticket_type_db_id'] ?? null;
        $ticketType = $ticketTypeId
            ? TicketType::query()->whereKey($ticketTypeId)->first()
            : TicketType::query()->where('event_id', $event->id)->orderBy('sort_order')->first();

        $regStatus = RegistrationStatus::tryFrom((string) ($row['registration_status'] ?? 'confirmed'))
            ?? RegistrationStatus::Confirmed;
        $payStatus = PaymentStatus::tryFrom((string) ($row['payment_status'] ?? 'paid'))
            ?? PaymentStatus::Paid;

        if ($regStatus === RegistrationStatus::AwaitingPayment) {
            // Imported pending-payment attendees stay without tickets until paid offline.
        }

        $isConfirmed = in_array($regStatus, [RegistrationStatus::Confirmed, RegistrationStatus::Pending], true)
            && $payStatus !== PaymentStatus::Failed;

        if ($payStatus === PaymentStatus::Paid || (float) ($ticketType?->price ?? 0) <= 0) {
            $isConfirmed = $regStatus !== RegistrationStatus::Cancelled
                && $regStatus !== RegistrationStatus::Refunded
                && $regStatus !== RegistrationStatus::Waitlisted;
            if ($isConfirmed && $regStatus === RegistrationStatus::Pending) {
                $regStatus = RegistrationStatus::Confirmed;
            }
        }

        $currency = $ticketType?->currency ?? (string) config('ems.defaults.currency', 'CAD');
        $price = (float) ($ticketType?->price ?? 0);
        $type = $price > 0 ? RegistrationType::Paid : RegistrationType::Free;

        $order = new Order();
        $order->reference = $this->codes->orderReference();
        $order->event_id = $event->id;
        $order->user_id = $import->imported_by;
        $order->buyer_name = $row['name'];
        $order->buyer_email = $row['email'];
        $order->buyer_phone = $row['phone'] ?? null;
        $order->total_amount = $isConfirmed ? $price : 0;
        $order->currency = $currency;
        $order->status = $isConfirmed ? OrderStatus::Completed : OrderStatus::Pending;
        $order->completed_at = $isConfirmed ? now() : null;
        $order->metadata = [
            'source' => 'imported',
            'import_id' => $import->id,
            'import_uuid' => $import->uuid,
        ];
        $order->save();

        if ($ticketType) {
            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->ticket_type_id = $ticketType->id;
            $item->name = $ticketType->name;
            $item->quantity = 1;
            $item->unit_price = $price;
            $item->line_total = $price;
            $item->currency = $currency;
            $item->save();

            if ($isConfirmed && $regStatus === RegistrationStatus::Confirmed) {
                $ticketType->quantity_sold = (int) $ticketType->quantity_sold + 1;
                $ticketType->save();
            }
        }

        $registration = new Registration();
        $registration->reference = $this->codes->registrationReference();
        $registration->event_id = $event->id;
        $registration->ticket_type_id = $ticketType?->id;
        $registration->order_id = $order->id;
        $registration->attendee_name = $row['name'];
        $registration->attendee_email = $row['email'];
        $registration->attendee_phone = $row['phone'] ?? null;
        $registration->status = $regStatus === RegistrationStatus::Pending && $isConfirmed
            ? RegistrationStatus::Confirmed
            : $regStatus;
        $registration->type = $type;
        $registration->quantity = 1;
        $registration->amount_due = $price;
        $registration->currency = $currency;
        $registration->registered_at = now();
        $registration->confirmed_at = $registration->status === RegistrationStatus::Confirmed ? now() : null;
        $registration->metadata = [
            'source' => 'imported',
            'import_id' => $import->id,
            'import_uuid' => $import->uuid,
            'imported_by' => $import->imported_by,
            'original_filename' => $import->original_filename,
            'is_member' => (bool) ($row['is_member'] ?? false),
            'payment_status' => $payStatus->value,
        ];
        $registration->save();

        RegistrationCreated::dispatch($registration, $import->importer);

        if ($registration->status === RegistrationStatus::Confirmed) {
            $this->tickets->issueFor($registration);
        }

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $mapping): array
    {
        $out = [];
        foreach (self::EMS_FIELDS as $field) {
            $column = $mapping[$field] ?? null;
            $out[$field] = $column !== null && $column !== '' ? ($row[$column] ?? null) : null;
        }

        // Sensible defaults when mapping omitted: try common header names.
        if (($out['name'] ?? null) === null) {
            foreach (['Name', 'Full Name', 'Attendee Name', 'name', 'full_name'] as $candidate) {
                if (isset($row[$candidate]) && trim((string) $row[$candidate]) !== '') {
                    $out['name'] = $row[$candidate];
                    break;
                }
            }
        }
        if (($out['email'] ?? null) === null) {
            foreach (['Email', 'E-mail', 'email', 'Email Address'] as $candidate) {
                if (isset($row[$candidate]) && trim((string) $row[$candidate]) !== '') {
                    $out['email'] = $row[$candidate];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TicketType>  $types
     */
    private function resolveTicketType($types, mixed $value): ?TicketType
    {
        if ($value === null || trim((string) $value) === '') {
            return $types->first();
        }

        $needle = strtolower(trim((string) $value));

        return $types->first(function (TicketType $type) use ($needle) {
            return strtolower($type->name) === $needle
                || strtolower((string) $type->uuid) === $needle
                || (string) $type->id === $needle;
        });
    }

    private function parseRegistrationStatus(mixed $value): ?RegistrationStatus
    {
        if ($value === null || trim((string) $value) === '') {
            return RegistrationStatus::Confirmed;
        }

        $key = strtolower(trim((string) $value));
        $map = [
            'pending payment' => RegistrationStatus::AwaitingPayment,
            'pending_payment' => RegistrationStatus::AwaitingPayment,
            'awaiting_payment' => RegistrationStatus::AwaitingPayment,
            'awaiting payment' => RegistrationStatus::AwaitingPayment,
            'registered' => RegistrationStatus::Confirmed,
            'confirmed' => RegistrationStatus::Confirmed,
            'waitlisted' => RegistrationStatus::Waitlisted,
            'waitlist' => RegistrationStatus::Waitlisted,
            'cancelled' => RegistrationStatus::Cancelled,
            'canceled' => RegistrationStatus::Cancelled,
            'refunded' => RegistrationStatus::Refunded,
            'pending' => RegistrationStatus::Pending,
        ];

        return $map[$key] ?? RegistrationStatus::tryFrom($key);
    }

    private function parsePaymentStatus(mixed $value): ?PaymentStatus
    {
        if ($value === null || trim((string) $value) === '') {
            return PaymentStatus::Paid;
        }

        $key = strtolower(trim((string) $value));
        $map = [
            'pending' => PaymentStatus::Pending,
            'paid' => PaymentStatus::Paid,
            'failed' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            'canceled' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
        ];

        return $map[$key] ?? PaymentStatus::tryFrom($key);
    }

    private function parseMemberStatus(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return false;
        }

        $key = strtolower(trim((string) $value));

        return in_array($key, ['1', 'true', 'yes', 'y', 'member'], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new EmsException('Unable to read CSV file.', [], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $headers = null;
        $rows = [];
        $rowNum = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($data)) {
                continue;
            }
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim((string) $h), $data);
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $assoc['__row'] = $rowNum;
            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
