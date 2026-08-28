<?php

namespace App\Ems\Jobs;

use App\Ems\Enums\AttendeeImportStatus;
use App\Ems\Models\AttendeeImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupStalePreviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('ems.operations.queue', 'ems-operations'));
    }

    public function handle(): void
    {
        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.imports.cleanup_started');

        $cleanedCount = 0;

        AttendeeImport::query()
            ->whereIn('status', [
                AttendeeImportStatus::Pending->value,
                AttendeeImportStatus::Validating->value,
                AttendeeImportStatus::Previewed->value,
            ])
            ->where('created_at', '<=', now()->subHours(24))
            ->chunk(50, function ($imports) use (&$cleanedCount): void {
                foreach ($imports as $import) {
                    $cleaned = DB::transaction(function () use ($import): bool {
                        /** @var AttendeeImport|null $locked */
                        $locked = AttendeeImport::query()
                            ->whereKey($import->id)
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return false;
                        }

                        // Guard against concurrent processing/completion
                        if (! in_array($locked->status, [
                            AttendeeImportStatus::Pending,
                            AttendeeImportStatus::Validating,
                            AttendeeImportStatus::Previewed,
                        ], true)) {
                            return false;
                        }

                        // Try to delete physical file if path exists in summary
                        $storedPath = data_get($locked->summary, 'stored_path');
                        if ($storedPath) {
                            $disk = config('ems.storage.disk', 'local');
                            try {
                                if (Storage::disk($disk)->exists($storedPath)) {
                                    Storage::disk($disk)->delete($storedPath);
                                }
                            } catch (\Throwable $e) {
                                Log::channel((string) config('ems.logging.channel', 'ems'))
                                    ->warning('ems.imports.cleanup_file_failed', [
                                        'import_uuid' => $locked->uuid,
                                        'stored_path' => $storedPath,
                                        'error' => $e->getMessage(),
                                    ]);
                            }
                        }

                        $locked->delete(); // Soft-delete the database record

                        return true;
                    });

                    if ($cleaned) {
                        $cleanedCount++;
                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->info('ems.imports.cleaned_up_stale_preview', [
                                'import_uuid' => $import->uuid,
                                'original_filename' => $import->original_filename,
                            ]);
                    }
                }
            });

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.imports.cleanup_completed', ['cleaned_count' => $cleanedCount]);
    }
}
