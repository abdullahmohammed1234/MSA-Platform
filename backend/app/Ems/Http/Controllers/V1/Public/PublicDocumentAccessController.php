<?php

namespace App\Ems\Http\Controllers\V1\Public;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\EventDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicDocumentAccessController extends EmsController
{
    /**
     * GET /event-documents/{documentUuid}/{token}
     *
     * Secure opaque document access controller. Validates document existence,
     * active state, event lifecycle, and token hash match before streaming
     * the private PDF content. Never exposes internal storage paths or 302 redirects.
     */
    public function access(string $documentUuid, string $token): Response
    {
        $token = trim($token);
        if ($token === '') {
            throw new NotFoundHttpException('Document not found.');
        }

        /** @var EventDocument|null $document */
        $document = EventDocument::query()
            ->where('uuid', $documentUuid)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with('event')
            ->first();

        if ($document === null || $document->event === null || $document->event->trashed()) {
            throw new NotFoundHttpException('Document not found.');
        }

        $expectedHash = $document->access_token_hash;
        $actualHash = hash('sha256', $token);

        if (! hash_equals($expectedHash, $actualHash)) {
            throw new NotFoundHttpException('Document not found.');
        }

        $disk = $document->storage_disk;
        $path = $document->storage_path;

        if (! Storage::disk($disk)->exists($path)) {
            throw new NotFoundHttpException('Document not found.');
        }

        $content = Storage::disk($disk)->get($path);

        if ($content === null || $content === false) {
            throw new NotFoundHttpException('Document not found.');
        }

        $filename = addslashes($document->original_filename ?: "{$document->name}.pdf");

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
