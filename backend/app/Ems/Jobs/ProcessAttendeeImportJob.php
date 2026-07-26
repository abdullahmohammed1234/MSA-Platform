<?php

namespace App\Ems\Jobs;

use App\Ems\Enums\AttendeeImportStatus;
use App\Ems\Models\AttendeeImport;
use App\Ems\Services\Operations\AttendeeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAttendeeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue((string) config('ems.operations.queue', 'ems-operations'));
    }

    public function handle(AttendeeImportService $imports): void
    {
        $import = AttendeeImport::query()->findOrFail($this->importId);
        $imports->processImport($import);
    }

    public function failed(?Throwable $exception): void
    {
        $import = AttendeeImport::query()->find($this->importId);
        if ($import === null) {
            return;
        }

        $import->status = AttendeeImportStatus::Failed;
        $import->error_message = $exception?->getMessage() ?? 'Import failed.';
        $import->completed_at = now();
        $import->save();
    }
}
