<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\ReplaceEventDocumentRequest;
use App\Ems\Http\Requests\StoreEventDocumentRequest;
use App\Ems\Http\Requests\UpdateEventDocumentRequest;
use App\Ems\Http\Resources\EventDocumentResource;
use App\Ems\Models\Event;
use App\Ems\Models\EventDocument;
use App\Ems\Services\EventDocumentService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventDocumentController extends EmsController
{
    public function __construct(
        private readonly EventDocumentService $documentService,
    ) {
    }

    /**
     * GET /api/v1/ems/events/{event}/documents
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewDocuments', $event);

        $documents = $event->documents()->with('uploader')->get();

        return ApiResponse::success(
            EventDocumentResource::collection($documents),
            'Event documents retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/documents
     */
    public function store(StoreEventDocumentRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageDocuments', $event);

        $result = $this->documentService->createDocument(
            $event,
            $request->validated(),
            $request->file('file'),
            $request->user()
        );

        return ApiResponse::created(
            [
                'document' => new EventDocumentResource($result['document']),
                'raw_token' => $result['raw_token'],
                'qr_url' => $result['qr_url'],
            ],
            'Event document uploaded successfully.'
        );
    }

    /**
     * PATCH /api/v1/ems/events/{event}/documents/{document}
     */
    public function update(
        UpdateEventDocumentRequest $request,
        Event $event,
        EventDocument $document
    ): JsonResponse {
        $this->ensureBelongsToEvent($event, $document);
        $this->authorize('manageDocuments', $event);

        $updated = $this->documentService->updateMetadata(
            $document,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new EventDocumentResource($updated),
            'Event document updated successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/documents/{document}/replace
     */
    public function replace(
        ReplaceEventDocumentRequest $request,
        Event $event,
        EventDocument $document
    ): JsonResponse {
        $this->ensureBelongsToEvent($event, $document);
        $this->authorize('manageDocuments', $event);

        $replaced = $this->documentService->replacePdf(
            $document,
            $request->file('file'),
            $request->user()
        );

        return ApiResponse::success(
            new EventDocumentResource($replaced),
            'Event document PDF replaced successfully.'
        );
    }

    /**
     * DELETE /api/v1/ems/events/{event}/documents/{document}
     */
    public function destroy(Request $request, Event $event, EventDocument $document): JsonResponse
    {
        $this->ensureBelongsToEvent($event, $document);
        $this->authorize('manageDocuments', $event);

        $this->documentService->deleteDocument($document, $request->user());

        return ApiResponse::deleted('Event document deleted successfully.');
    }

    /**
     * POST /api/v1/ems/events/{event}/documents/{document}/rotate-access
     */
    public function rotateAccess(Request $request, Event $event, EventDocument $document): JsonResponse
    {
        $this->ensureBelongsToEvent($event, $document);
        $this->authorize('manageDocuments', $event);

        $result = $this->documentService->rotateAccessToken($document, $request->user());

        return ApiResponse::success(
            [
                'document' => new EventDocumentResource($result['document']),
                'raw_token' => $result['raw_token'],
                'qr_url' => $result['qr_url'],
            ],
            'Access token rotated successfully. Previous QR code invalidated.'
        );
    }

    /**
     * GET /api/v1/ems/events/{event}/documents/{document}/qr
     */
    public function showQr(Request $request, Event $event, EventDocument $document): JsonResponse
    {
        $this->ensureBelongsToEvent($event, $document);
        $this->authorize('viewDocuments', $event);

        // If requested with a valid token query param or rotate flag, use or generate token
        $rawToken = (string) $request->query('token', '');
        if ($rawToken === '') {
            // Generate a fresh temporary token session for viewing QR if user manages documents,
            // or rotate access to get a clean raw_token
            $result = $this->documentService->rotateAccessToken($document, $request->user());
            $rawToken = $result['raw_token'];
            $document = $result['document'];
        }

        $qrUrl = $this->documentService->generateAccessUrl($document, $rawToken);
        $qrDataUri = $this->documentService->generateQrDataUri($qrUrl);

        return ApiResponse::success(
            [
                'document' => new EventDocumentResource($document),
                'qr_url' => $qrUrl,
                'qr_data_uri' => $qrDataUri,
                'raw_token' => $rawToken,
            ],
            'QR code generated successfully.'
        );
    }

    /**
     * Enforce event ownership and prevent cross-event IDOR.
     */
    private function ensureBelongsToEvent(Event $event, EventDocument $document): void
    {
        if ((int) $document->event_id !== (int) $event->id) {
            throw new NotFoundHttpException('The requested document does not belong to this event.');
        }
    }
}
