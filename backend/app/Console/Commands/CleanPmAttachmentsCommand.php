<?php

namespace App\Console\Commands;

use App\Pm\Models\PmAttachment;
use App\Pm\Models\PmAttachmentCleanup;
use App\Pm\Models\PmProject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanPmAttachmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pm:clean-attachments
                            {--project= : Optional project UUID to scope reconciliation}
                            {--dry-run : Report what would be cleaned without deleting files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile and retry failed attachment unlinks and clean orphaned files from pm_attachments storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projectUuid = $this->option('project');
        $isDryRun = (bool) $this->option('dry-run');

        $targetProject = null;
        if ($projectUuid) {
            $targetProject = PmProject::where('uuid', $projectUuid)->first();
            if (!$targetProject) {
                $this->error("Project with UUID '{$projectUuid}' not found.");
                return 1;
            }
        }

        $this->info('Starting PMS attachment cleanup reconciliation' . ($isDryRun ? ' [DRY RUN]' : '') . '...');

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

            if (!str_starts_with($filePath, 'pm_attachments/')) {
                $this->warn("Skipping '{$filePath}': outside pm_attachments directory confinement.");
                $failedCount++;
                continue;
            }

            if (PmAttachment::withTrashed()->where('file_path', $filePath)->exists()) {
                $this->line("Protected: '{$filePath}' belongs to an active or soft-deleted attachment record.");
                $protectedCount++;
                if (!$isDryRun) {
                    $cleanup->update([
                        'status' => 'completed',
                        'last_error' => 'Skipped: file belongs to an active or soft-deleted attachment.',
                    ]);
                }
                continue;
            }

            if ($isDryRun) {
                $this->line("[DRY RUN] Would unlink orphaned file: {$filePath}");
                $reapedCount++;
                continue;
            }

            try {
                $disk = Storage::disk($cleanup->disk ?: 'local');
                if (!$disk->exists($filePath) || $disk->delete($filePath)) {
                    $cleanup->delete();
                    $reapedCount++;
                    $this->info("Successfully deleted: {$filePath}");
                } else {
                    $cleanup->increment('attempts');
                    $cleanup->update([
                        'status' => 'failed',
                        'last_error' => 'Storage disk returned false on delete retry.',
                    ]);
                    $failedCount++;
                    $this->error("Failed to delete: {$filePath}");
                }
            } catch (\Throwable $e) {
                $cleanup->increment('attempts');
                $cleanup->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
                $failedCount++;
                $this->error("Exception deleting '{$filePath}': " . $e->getMessage());
            }
        }

        // 2. Scan directory for unreferenced orphans
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

                if ($isDryRun) {
                    $this->line("[DRY RUN] Would reap unindexed file: {$relativeFilePath}");
                    $reapedCount++;
                    continue;
                }

                try {
                    if ($disk->delete($relativeFilePath)) {
                        $reapedCount++;
                        PmAttachmentCleanup::where('file_path', $relativeFilePath)->delete();
                        $this->info("Reaped unindexed orphan: {$relativeFilePath}");
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    $this->error("Failed to reap orphan '{$relativeFilePath}': " . $e->getMessage());
                }
            }
        }

        $this->info("Reconciliation complete. Reaped: {$reapedCount}, Protected active: {$protectedCount}, Failed: {$failedCount}");
        return 0;
    }
}

