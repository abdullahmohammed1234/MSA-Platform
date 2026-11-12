<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmAttachment;
use App\Pm\Models\PmAttachmentCleanup;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmAttachmentController extends Controller
{
    /**
     * Maximum allowed file size: 25 MB (in kilobytes).
     */
    protected const MAX_FILE_SIZE_KB = 25600;

    /**
     * Strict list of approved file extensions (SEC-11-02).
     */
    protected const ALLOWED_EXTENSIONS = [
        'pdf', 'docx', 'xlsx', 'pptx', 'zip',
        'jpg', 'jpeg', 'png', 'webp', 'csv', 'txt',
    ];

    /**
     * Strict mapping of allowed extensions to valid detected MIME types.
     */
    protected const ALLOWED_MIME_TYPES = [
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/x-zip'],
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png', 'image/x-png'],
        'webp' => ['image/webp'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'txt'  => ['text/plain'],
    ];

    /**
     * Executable and active script formats prohibited in double-extension attacks.
     */
    protected const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bash', 'bat', 'cmd', 'com', 'msi', 'dll', 'bin',
        'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'ps2', 'psm1',
        'py', 'cgi', 'pl', 'jsp', 'asp', 'aspx', 'jar',
        'scr', 'reg', 'hta', 'cpl', 'inf', 'js', 'html', 'htm', 'svg', 'xml',
    ];

    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * List all attachments for a project.
     */
    public function indexProject(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $attachments = PmAttachment::with(['uploader:id,name,email,avatar', 'task:id,uuid,title'])
            ->where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'attachments' => $attachments->map(fn (PmAttachment $att) => $this->formatAttachment($att)),
        ]);
    }

    /**
     * List attachments for a specific task.
     */
    public function indexTask(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $task->project)) {
            abort(403, 'Unauthorized.');
        }

        $attachments = PmAttachment::with('uploader:id,name,email,avatar')
            ->where('task_id', $task->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'attachments' => $attachments->map(fn (PmAttachment $att) => $this->formatAttachment($att)),
        ]);
    }

    /**
     * Upload an attachment to a project.
     */
    public function storeProject(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canUploadAttachment($user, $project)) {
            abort(403, 'Unauthorized: Viewers cannot upload project attachments.');
        }

        return $this->handleUpload($request, $project, null, $user);
    }

    /**
     * Upload an attachment to a task.
     */
    public function storeTask(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canUploadAttachment($user, $task->project)) {
            abort(403, 'Unauthorized: Viewers cannot upload task attachments.');
        }

        return $this->handleUpload($request, $task->project, $task, $user);
    }

    /**
     * Stream an attachment download securely.
     */
    public function download(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $attachment = PmAttachment::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canDownloadAttachment($user, $attachment)) {
            abort(403, 'Unauthorized: You do not have permission to view or download files from this project.');
        }

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (!$disk->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'Attachment file not found in storage.',
            ], 404);
        }

        $headers = [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $attachment->file_size,
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"',
                str_replace(['"', "\r", "\n"], '', $attachment->original_name)
            ),
            'X-Content-Type-Options' => 'nosniff',
        ];

        return response()->stream(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Soft delete an authorized attachment (SEC-11-03).
     * Retains the physical file on disk for restoration.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $attachment = PmAttachment::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canDeleteAttachment($user, $attachment)) {
            abort(403, 'Unauthorized: Only the original uploader or project managers/owners can delete attachments.');
        }

        $projectId = $attachment->project_id;
        $originalName = $attachment->original_name;

        // Soft delete only: retain the physical file on disk
        $attachment->delete();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'attachment_deleted',
            subjectType: 'attachment',
            subjectId: null,
            projectId: $projectId,
            metadata: [
                'original_name' => $originalName,
                'lifecycle' => 'soft_deleted',
            ]
        );

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Restore a soft-deleted attachment (SEC-11-03).
     * Physical file was preserved on disk and becomes downloadable again.
     */
    public function restore(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $attachment = PmAttachment::withTrashed()->with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$attachment->trashed()) {
            return response()->json([
                'message' => 'Attachment is not deleted.',
            ], 422);
        }

        if (!$this->accessService->canUpdateProject($user, $attachment->project)) {
            abort(403, 'Unauthorized: Only project managers or owners can restore deleted attachments.');
        }

        $attachment->restore();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'attachment_restored',
            subjectType: 'attachment',
            subjectId: $attachment->id,
            projectId: $attachment->project_id,
            metadata: [
                'original_name' => $attachment->original_name,
            ]
        );

        return response()->json([
            'message' => 'Attachment restored successfully.',
            'attachment' => $this->formatAttachment($attachment->fresh(['uploader:id,name,email,avatar'])),
        ]);
    }

    /**
     * Permanently force-delete an attachment and its physical file (SEC-11-03).
     * Two-phase commit with durable cleanup intent recording for failure recovery.
     */
    public function forceDestroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $attachment = PmAttachment::withTrashed()->with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateProject($user, $attachment->project)) {
            abort(403, 'Unauthorized: Only project managers or owners can permanently delete attachments.');
        }

        $projectId = $attachment->project_id;
        $originalName = $attachment->original_name;
        $filePath = $attachment->file_path;
        $diskName = $attachment->disk ?: 'local';

        // 1. Transactionally delete database record, record audit log, and record durable cleanup intent
        $cleanupRecord = \Illuminate\Support\Facades\DB::transaction(function () use ($attachment, $user, $projectId, $originalName, $filePath, $diskName) {
            $attachment->forceDelete();

            PmActivityLog::record(
                actorId: $user->id,
                action: 'attachment_permanently_deleted',
                subjectType: 'attachment',
                subjectId: null,
                projectId: $projectId,
                metadata: [
                    'original_name' => $originalName,
                    'lifecycle' => 'force_deleted',
                ]
            );

            return PmAttachmentCleanup::create([
                'project_id' => $projectId,
                'file_path' => $filePath,
                'disk' => $diskName,
                'attempts' => 0,
                'status' => 'pending',
            ]);
        });

        // 2. Unlink physical file only after database commit succeeds
        $fileDeleted = false;
        try {
            $disk = Storage::disk($diskName);
            if (!$disk->exists($filePath) || $disk->delete($filePath)) {
                $fileDeleted = true;
            } else {
                $cleanupRecord->update([
                    'attempts' => 1,
                    'status' => 'failed',
                    'last_error' => 'Storage disk returned false on physical file delete.',
                ]);
            }
        } catch (\Throwable $e) {
            $cleanupRecord->update([
                'attempts' => 1,
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            \Illuminate\Support\Facades\Log::warning("PMS attachment physical unlinking failed for {$filePath}: " . $e->getMessage());
        }

        if ($fileDeleted) {
            $cleanupRecord->delete();
        }

        return response()->json([
            'message' => 'Attachment permanently deleted.',
            'cleanup_status' => $fileDeleted ? 'completed' : 'pending_reconciliation',
        ]);
    }

    /**
     * Reconcile and retry failed attachment cleanups (SEC-11-03 Recovery).
     * Strictly confined to pm_attachments/ directory and protects active attachment records.
     */
    public function reconcileCleanup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $projectUuid = $request->input('project_uuid');
        $targetProject = null;

        if ($projectUuid) {
            $targetProject = PmProject::where('uuid', $projectUuid)->firstOrFail();
            if (!$this->accessService->canUpdateProject($user, $targetProject)) {
                abort(403, 'Unauthorized: Only project managers or owners can reconcile project attachment cleanups.');
            }
        } else {
            if (!$user->hasRole('super-admin') && !$user->hasRole('pms-administrator')) {
                abort(403, 'Unauthorized: Only PMS administrators can trigger global attachment cleanup.');
            }
        }

        $reapedCount = 0;
        $protectedCount = 0;
        $failedCount = 0;

        // 1. Process durable cleanup queue records
        $cleanupQuery = PmAttachmentCleanup::whereIn('status', ['pending', 'failed']);
        if ($targetProject) {
            $cleanupQuery->where('project_id', $targetProject->id);
        }
        $pendingCleanups = $cleanupQuery->get();

        foreach ($pendingCleanups as $cleanup) {
            $filePath = $cleanup->file_path;

            // Security: Strictly enforce directory confinement to pm_attachments/
            if (!str_starts_with($filePath, 'pm_attachments/')) {
                $cleanup->update([
                    'status' => 'failed',
                    'last_error' => 'Path traversal or unauthorized directory confinement breach prevented.',
                ]);
                $failedCount++;
                continue;
            }

            // Invariant: NEVER delete a file referenced by an active or soft-deleted attachment record
            if (PmAttachment::withTrashed()->where('file_path', $filePath)->exists()) {
                $cleanup->update([
                    'status' => 'completed',
                    'last_error' => 'Skipped: file belongs to an active or soft-deleted attachment.',
                ]);
                $protectedCount++;
                continue;
            }

            try {
                $disk = Storage::disk($cleanup->disk ?: 'local');
                if (!$disk->exists($filePath) || $disk->delete($filePath)) {
                    $cleanup->delete();
                    $reapedCount++;
                } else {
                    $cleanup->increment('attempts');
                    $cleanup->update([
                        'status' => 'failed',
                        'last_error' => 'Storage disk returned false on delete retry.',
                    ]);
                    $failedCount++;
                }
            } catch (\Throwable $e) {
                $cleanup->increment('attempts');
                $cleanup->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
                $failedCount++;
            }
        }

        // 2. Directory scan reconciliation for orphaned files in target directory
        $scanDir = $targetProject ? "pm_attachments/{$targetProject->uuid}" : 'pm_attachments';
        $disk = Storage::disk('local');

        if ($disk->exists($scanDir)) {
            $allFiles = $disk->allFiles($scanDir);
            foreach ($allFiles as $relativeFilePath) {
                if (!str_starts_with($relativeFilePath, 'pm_attachments/')) {
                    continue;
                }

                if (PmAttachment::withTrashed()->where('file_path', $relativeFilePath)->exists()) {
                    $protectedCount++;
                    continue;
                }

                try {
                    if ($disk->delete($relativeFilePath)) {
                        $reapedCount++;
                        PmAttachmentCleanup::where('file_path', $relativeFilePath)->delete();
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                }
            }
        }

        return response()->json([
            'message' => 'Attachment reconciliation completed successfully.',
            'reaped_files_count' => $reapedCount,
            'protected_active_count' => $protectedCount,
            'failed_retries_count' => $failedCount,
        ]);
    }

    /**
     * Shared upload processing logic with strict allowlist and content-based validation (SEC-11-02).
     */
    protected function handleUpload(Request $request, PmProject $project, ?PmTask $task, User $user): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_FILE_SIZE_KB,
            ],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        // 1. Filename formatting guards: reject trailing dots or whitespace
        if (str_ends_with($originalName, '.') || str_ends_with($originalName, ' ')) {
            return response()->json([
                'message' => 'Filenames with trailing dots or spaces are not permitted.',
            ], 422);
        }

        // 2. Double extension check: detect hidden executables like exploit.php.png or script.exe.jpg
        $nameParts = explode('.', $originalName);
        if (count($nameParts) > 2) {
            $intermediateParts = array_slice($nameParts, 1, -1);
            foreach ($intermediateParts as $part) {
                if (in_array(strtolower($part), self::DANGEROUS_EXTENSIONS, true)) {
                    return response()->json([
                        'message' => 'Double-extension files containing executable extensions are prohibited for security reasons.',
                    ], 422);
                }
            }
        }

        // 3. Strict Extension Allowlist Check
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json([
                'message' => "Files with extension '.{$extension}' are prohibited. Only approved document, image, and data formats are permitted.",
            ], 422);
        }

        // 4. Content-Based MIME Inspection
        $mimeType = strtolower($file->getMimeType() ?: 'application/octet-stream');

        // Disallow dangerous active content MIME types (SVG scripts, HTML, PHP, shell)
        $prohibitedActiveMimes = [
            'image/svg+xml',
            'text/html',
            'application/xhtml+xml',
            'application/xml',
            'text/xml',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/x-executable',
            'application/x-msdos-program',
            'application/x-sh',
            'text/x-shellscript',
            'application/javascript',
            'text/javascript',
            'application/x-dosexec',
        ];

        if (in_array($mimeType, $prohibitedActiveMimes, true)) {
            return response()->json([
                'message' => "Uploaded file contains active content or prohibited MIME type '{$mimeType}'.",
            ], 422);
        }

        // 5. Validate MIME matches the declared extension
        $allowedMimesForExtension = self::ALLOWED_MIME_TYPES[$extension] ?? [];
        if (!in_array($mimeType, $allowedMimesForExtension, true)) {
            return response()->json([
                'message' => "MIME type mismatch: detected '{$mimeType}' which is not a valid format for extension '.{$extension}'.",
            ], 422);
        }

        // 6. Deep OpenXML validation for Office documents (prevent arbitrary ZIP masquerading as DOCX/XLSX/PPTX)
        if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            $zip = new \ZipArchive();
            $realPath = $file->getRealPath();
            if ($zip->open($realPath) === true) {
                $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
                $hasOfficePayload = match ($extension) {
                    'docx' => $zip->locateName('word/document.xml') !== false,
                    'xlsx' => $zip->locateName('xl/workbook.xml') !== false,
                    'pptx' => $zip->locateName('ppt/presentation.xml') !== false,
                };
                $zip->close();

                if (!$hasContentTypes || !$hasOfficePayload) {
                    return response()->json([
                        'message' => "The uploaded file is not a valid {$extension} document structure.",
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => "Malformed or unreadable {$extension} document.",
                ], 422);
            }
        }

        // 7. Active script & markup smuggling guard for plain text and CSV files
        if (in_array($extension, ['txt', 'csv'], true)) {
            $contents = file_get_contents($file->getRealPath(), false, null, 0, 4096);
            if ($contents !== false && preg_match('/<\s*(?:script|iframe|object|embed|html|body|svg)\b/i', $contents)) {
                return response()->json([
                    'message' => 'Text file contains prohibited active markup or script tags.',
                ], 422);
            }
        }

        // 5. Safe randomized storage name
        $storageUuid = (string) Str::uuid();
        $safeStorageName = "{$storageUuid}.{$extension}";
        $storageDirectory = "pm_attachments/{$project->uuid}";

        // Store privately on local disk (outside web root)
        $path = $file->storeAs($storageDirectory, $safeStorageName, 'local');

        $attachment = PmAttachment::create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'user_id' => $user->id,
            'original_name' => $originalName,
            'storage_name' => $safeStorageName,
            'file_path' => $path,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
        ]);

        PmActivityLog::record(
            actorId: $user->id,
            action: 'attachment_uploaded',
            subjectType: 'attachment',
            subjectId: $attachment->id,
            projectId: $project->id,
            metadata: [
                'original_name' => $attachment->original_name,
                'file_size' => $attachment->file_size,
                'mime_type' => $attachment->mime_type,
                'task_id' => $task?->id,
                'task_title' => $task?->title,
            ]
        );

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'attachment' => $this->formatAttachment($attachment->load('uploader:id,name,email,avatar')),
        ], 201);
    }

    protected function formatAttachment(PmAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'uuid' => $attachment->uuid,
            'project_id' => $attachment->project_id,
            'task_id' => $attachment->task_id,
            'task' => $attachment->task ? [
                'id' => $attachment->task->id,
                'uuid' => $attachment->task->uuid,
                'title' => $attachment->task->title,
            ] : null,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'uploader' => $attachment->uploader ? [
                'id' => $attachment->uploader->id,
                'name' => $attachment->uploader->name,
                'email' => $attachment->uploader->email,
                'avatar' => $attachment->uploader->avatar,
            ] : null,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
