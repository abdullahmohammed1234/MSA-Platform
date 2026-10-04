<?php

namespace App\Ems\Services;

use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\EventDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EventDocumentService
{
    public function __construct(
        private readonly EmsActivityLogger $activity,
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    /**
     * Create and store a new supplementary event document.
     *
     * @param array{
     *     name: string,
     *     document_type: string,
     *     description?: string|null,
     *     sort_order?: int
     * } $data
     * @return array{document: EventDocument, raw_token: string, qr_url: string}
     */
    public function createDocument(Event $event, array $data, UploadedFile $file, User $actor): array
    {
        $this->verifyPdfContent($file);

        $docUuid = (string) Str::uuid();
        $rawToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $rawToken);

        $disk = (string) config('ems.documents.disk', 'ems_documents');
        $directory = "events/{$event->uuid}/documents";
        $filename = "{$docUuid}.pdf";

        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if (! $storedPath) {
            throw new EmsException(
                'Failed to store document file in private storage.',
                ['file' => ['Unable to save file to disk.']],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $storagePath = $storedPath;

        try {
            $document = DB::transaction(function () use ($event, $data, $file, $docUuid, $disk, $storagePath, $tokenHash, $actor) {
                return EventDocument::create([
                    'event_id' => $event->id,
                    'uuid' => $docUuid,
                    'name' => trim($data['name']),
                    'document_type' => $data['document_type'],
                    'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_disk' => $disk,
                    'storage_path' => $storagePath,
                    'mime_type' => 'application/pdf',
                    'file_size' => $file->getSize(),
                    'access_token_hash' => $tokenHash,
                    'is_active' => true,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'uploaded_by' => $actor->id,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($storagePath);
            throw $e;
        }

        $this->activity->log(
            'document.created',
            $document,
            'Event document uploaded.',
            [
                'document_uuid' => $document->uuid,
                'event_uuid' => $event->uuid,
                'name' => $document->name,
                'type' => $document->document_type,
            ]
        );

        $qrUrl = $this->generateAccessUrl($document, $rawToken);

        return [
            'document' => $document,
            'raw_token' => $rawToken,
            'qr_url' => $qrUrl,
        ];
    }

    /**
     * Replace the PDF file of an existing document while retaining UUID and access token.
     */
    public function replacePdf(EventDocument $document, UploadedFile $file, User $actor): EventDocument
    {
        $this->verifyPdfContent($file);

        $disk = $document->storage_disk;
        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            throw new EmsException(
                'Unable to read uploaded replacement file.',
                ['file' => ['File read failure.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        Storage::disk($disk)->put($document->storage_path, $content);

        $document->original_filename = $file->getClientOriginalName();
        $document->file_size = $file->getSize();
        $document->mime_type = 'application/pdf';
        $document->touch();
        $document->save();

        $this->activity->log(
            'document.replaced',
            $document,
            'Event document PDF replaced.',
            [
                'document_uuid' => $document->uuid,
                'event_uuid' => $document->event?->uuid,
                'new_filename' => $document->original_filename,
                'file_size' => $document->file_size,
            ]
        );

        return $document->fresh();
    }

    /**
     * Update metadata fields for an existing event document.
     *
     * @param array{
     *     name?: string,
     *     document_type?: string,
     *     description?: string|null,
     *     sort_order?: int,
     *     is_active?: bool
     * } $data
     */
    public function updateMetadata(EventDocument $document, array $data, User $actor): EventDocument
    {
        if (isset($data['name'])) {
            $document->name = trim($data['name']);
        }
        if (isset($data['document_type'])) {
            $document->document_type = $data['document_type'];
        }
        if (array_key_exists('description', $data)) {
            $document->description = $data['description'] !== null ? trim((string) $data['description']) : null;
        }
        if (isset($data['sort_order'])) {
            $document->sort_order = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $document->is_active = (bool) $data['is_active'];
        }

        $document->save();

        $this->activity->log(
            'document.updated',
            $document,
            'Event document metadata updated.',
            [
                'document_uuid' => $document->uuid,
                'event_uuid' => $document->event?->uuid,
            ]
        );

        return $document->fresh();
    }

    /**
     * Delete an event document and remove its physical file from storage.
     */
    public function deleteDocument(EventDocument $document, User $actor): bool
    {
        $docUuid = $document->uuid;
        $eventUuid = $document->event?->uuid;
        $disk = $document->storage_disk;
        $path = $document->storage_path;

        DB::transaction(function () use ($document) {
            $document->delete();
        });

        Storage::disk($disk)->delete($path);

        $this->activity->log(
            'document.deleted',
            $document,
            'Event document deleted.',
            [
                'document_uuid' => $docUuid,
                'event_uuid' => $eventUuid,
            ]
        );

        return true;
    }

    /**
     * Regenerate the high-entropy access token for a document, invalidating previous QR codes.
     *
     * @return array{document: EventDocument, raw_token: string, qr_url: string}
     */
    public function rotateAccessToken(EventDocument $document, User $actor): array
    {
        $rawToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $rawToken);

        $document->access_token_hash = $tokenHash;
        $document->save();

        $this->activity->log(
            'document.token_rotated',
            $document,
            'Event document access token rotated.',
            [
                'document_uuid' => $document->uuid,
                'event_uuid' => $document->event?->uuid,
            ]
        );

        $qrUrl = $this->generateAccessUrl($document, $rawToken);

        return [
            'document' => $document->fresh(),
            'raw_token' => $rawToken,
            'qr_url' => $qrUrl,
        ];
    }

    /**
     * Generate the secure canonical access URL for a document.
     */
    public function generateAccessUrl(EventDocument $document, string $rawToken): string
    {
        $baseUrl = rtrim((string) config('app.url', config('ems.public.frontend_url', 'http://localhost:8000')), '/');

        return $baseUrl . '/event-documents/' . $document->uuid . '/' . $rawToken;
    }

    /**
     * Generate QR PNG data URI for a given document access URL.
     */
    public function generateQrDataUri(string $url, int $size = 280): string
    {
        return $this->qrCodeService->generateDataUriForUrl($url, $size);
    }

    /**
     * Generate QR SVG string for a given document access URL.
     */
    public function generateQrSvg(string $url, int $size = 280): string
    {
        return $this->qrCodeService->generateSvgForUrl($url, $size);
    }

    /**
     * Validate that the file is actually a PDF (checking magic bytes %PDF-).
     */
    private function verifyPdfContent(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new EmsException(
                'Invalid file upload.',
                ['file' => ['File does not exist.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            throw new EmsException(
                'Unable to read uploaded file.',
                ['file' => ['File read error.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new EmsException(
                'The uploaded file is not a valid PDF document.',
                ['file' => ['Only valid PDF documents are allowed.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
