<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Enums\CheckInMethod;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Operations\CheckInRequest;
use App\Ems\Http\Requests\Operations\CommitImportRequest;
use App\Ems\Http\Requests\Operations\ManualCheckInRequest;
use App\Ems\Http\Requests\Operations\PreviewImportRequest;
use App\Ems\Http\Requests\Operations\SaveImportMappingRequest;
use App\Ems\Http\Requests\Operations\UndoCheckInRequest;
use App\Ems\Http\Requests\Operations\ValidateTicketRequest;
use App\Ems\Http\Requests\Operations\WalkInRequest;
use App\Ems\Http\Resources\AttendeeResource;
use App\Ems\Http\Resources\CheckInResource;
use App\Ems\Http\Resources\Public\PublicRegistrationResource;
use App\Ems\Http\Resources\Public\PublicTicketResource;
use App\Ems\Models\Event;
use App\Ems\Services\Operations\AttendeeImportService;
use App\Ems\Services\Operations\AttendeeService;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Operations\EventOperationsService;
use App\Ems\Services\Operations\TicketValidationService;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventOperationsController extends EmsController
{
    public function __construct(
        private readonly EventOperationsService $operations,
        private readonly AttendeeService $attendees,
        private readonly CheckInService $checkIns,
        private readonly TicketValidationService $validator,
        private readonly AttendeeImportService $imports,
    ) {
    }

    public function operations(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewOperations', $event);

        $includePayments = $request->user()?->hasPermission(EmsPermissions::TICKETS_VIEW)
            || $request->user()?->hasPermission(EmsPermissions::EVENTS_UPDATE);

        return ApiResponse::success(
            $this->operations->summary($event, (bool) $includePayments),
            'Event operations summary retrieved.'
        );
    }

    public function attendees(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewAttendees', $event);

        $paginator = $this->attendees->paginate($event, $request->query());

        return ApiResponse::paginated(
            $paginator,
            'Attendees retrieved successfully.',
            AttendeeResource::class
        );
    }

    public function recentCheckIns(Event $event): JsonResponse
    {
        $this->authorize('viewOperations', $event);

        return ApiResponse::success(
            $this->operations->recentCheckIns($event),
            'Recent check-ins retrieved.'
        );
    }

    public function validateTicket(ValidateTicketRequest $request, Event $event): JsonResponse
    {
        $this->authorize('performCheckIn', $event);

        try {
            $result = $this->validator->validate($event, $request->validated('code'));
        } catch (CheckInException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => array_merge($e->errors(), [
                    'code' => [$e->resultCode()],
                ]),
                'data' => [
                    'ok' => false,
                    'code' => $e->resultCode(),
                    'message' => $e->getMessage(),
                    'previous_check_in' => $e->context() ?: null,
                ],
            ], $e->status());
        }

        return ApiResponse::success([
            'ok' => true,
            'code' => 'valid',
            'message' => $result['message'],
            'ticket' => new PublicTicketResource($result['ticket']),
            'registration' => new PublicRegistrationResource($result['registration']),
            'previous_check_in' => null,
        ], 'Ticket is valid.');
    }

    public function checkIn(CheckInRequest $request, Event $event): JsonResponse
    {
        $this->authorize('performCheckIn', $event);

        $data = $request->validated();
        $method = CheckInMethod::tryFrom($data['method'] ?? CheckInMethod::QrScan->value) ?? CheckInMethod::QrScan;

        try {
            $result = $this->checkIns->checkInByCode(
                $event,
                $data['code'],
                $request->user(),
                $method,
                $data['device'] ?? null,
                (bool) ($data['override'] ?? false),
                $request->ip(),
            );
        } catch (CheckInException $e) {
            return $this->checkInError($e);
        }

        return ApiResponse::success([
            'ok' => true,
            'code' => 'checked_in',
            'message' => 'Checked in successfully.',
            'check_in' => new CheckInResource($result['check_in']),
            'ticket' => new PublicTicketResource($result['ticket']),
            'registration' => new PublicRegistrationResource($result['registration']),
        ], 'Checked in successfully.');
    }

    public function manualCheckIn(ManualCheckInRequest $request, Event $event): JsonResponse
    {
        $this->authorize('performCheckIn', $event);
        $data = $request->validated();

        try {
            $result = $this->checkIns->manualCheckIn(
                $event,
                $request->user(),
                $data['registration_uuid'] ?? null,
                $data['ticket_code'] ?? null,
                $data['device'] ?? null,
                $request->ip(),
            );
        } catch (CheckInException $e) {
            return $this->checkInError($e);
        }

        return ApiResponse::success([
            'ok' => true,
            'code' => 'checked_in',
            'message' => 'Checked in successfully.',
            'check_in' => new CheckInResource($result['check_in']),
            'ticket' => isset($result['ticket']) ? new PublicTicketResource($result['ticket']) : null,
            'registration' => new PublicRegistrationResource($result['registration']),
        ], 'Checked in successfully.');
    }

    public function walkIn(WalkInRequest $request, Event $event): JsonResponse
    {
        $this->authorize('createRegistration', $event);
        $data = $request->validated();

        $result = $this->checkIns->walkIn($event, $data, $request->user(), $request->ip());

        return ApiResponse::created([
            'registration' => new PublicRegistrationResource($result['registration']),
            'tickets' => PublicTicketResource::collection($result['tickets']),
            'check_in' => $result['check_in'] ? new CheckInResource($result['check_in']) : null,
            'checkout_url' => $result['checkout_url'],
        ], $result['checkout_url']
            ? 'Walk-in created. Complete payment to issue the ticket.'
            : 'Walk-in registered successfully.');
    }

    public function undoCheckIn(UndoCheckInRequest $request, Event $event): JsonResponse
    {
        $this->authorize('undoCheckIn', $event);
        $data = $request->validated();

        $result = $this->checkIns->undoCheckIn(
            $event,
            $request->user(),
            $data['check_in_uuid'] ?? null,
            $data['ticket_code'] ?? null,
            $data['reason'] ?? '',
            $request->ip(),
        );

        return ApiResponse::success([
            'ok' => true,
            'code' => 'undone',
            'message' => 'Check-in undone.',
            'audit_uuid' => $result['audit']->uuid,
        ], 'Check-in undone.');
    }

    public function previewImport(PreviewImportRequest $request, Event $event): JsonResponse
    {
        $this->authorize('importAttendees', $event);

        $mapping = $request->mappingArray();
        $result = $this->imports->preview(
            $event,
            $request->file('file'),
            $mapping,
            $request->user()
        );

        return ApiResponse::success($result['preview'], 'Import preview ready.');
    }

    public function commitImport(CommitImportRequest $request, Event $event): JsonResponse
    {
        $this->authorize('importAttendees', $event);

        $result = $this->imports->commit(
            $event,
            $request->validated('import_uuid'),
            $request->user()
        );

        return ApiResponse::success([
            'import_uuid' => $result['import']->uuid,
            'status' => $result['import']->status->value,
            'queued' => $result['queued'],
            'summary' => $result['import']->summary,
        ], $result['queued'] ? 'Import queued for processing.' : 'Import completed.');
    }

    public function listMappings(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewImports', $event);

        $mappings = $this->imports->listMappings($event, $request->user());

        return ApiResponse::success(
            $mappings->map(fn ($m) => [
                'uuid' => $m->uuid,
                'name' => $m->name,
                'mapping' => $m->mapping,
            ]),
            'Column mappings retrieved.'
        );
    }

    public function saveMapping(SaveImportMappingRequest $request, Event $event): JsonResponse
    {
        $this->authorize('importAttendees', $event);
        $data = $request->validated();

        $mapping = $this->imports->saveMapping(
            $event,
            $request->user(),
            $data['name'],
            $data['mapping']
        );

        return ApiResponse::created([
            'uuid' => $mapping->uuid,
            'name' => $mapping->name,
            'mapping' => $mapping->mapping,
        ], 'Column mapping saved.');
    }

    private function checkInError(CheckInException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => $e->errors() ?: (object) [],
            'data' => [
                'ok' => false,
                'code' => $e->resultCode(),
                'message' => $e->getMessage(),
                'previous_check_in' => $e->context() ?: null,
            ],
        ], $e->status());
    }
}
