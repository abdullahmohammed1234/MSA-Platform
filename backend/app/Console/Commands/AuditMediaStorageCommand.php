<?php

namespace App\Console\Commands;

use App\Models\CMS\Media;
use App\Services\CMS\MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditMediaStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:audit-media-storage
                            {--fix : Attempt safe remediation by relocating legacy exposed files}
                            {--dry-run : Only report without modifying any files or records (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit CMS media storage isolation and identify legacy restricted assets in public storage';

    /**
     * Execute the console command.
     */
    public function handle(MediaService $mediaService): int
    {
        $shouldFix = (bool) $this->option('fix');
        $isDryRun = (bool) $this->option('dry-run') || !$shouldFix;

        $this->info('Scanning CMS media storage isolation...');
        if ($isDryRun) {
            $this->comment('Running in DRY-RUN mode. No files or database records will be modified.');
        } else {
            $this->warn('Running in FIX mode. Legacy exposed files will be moved to private storage.');
        }

        $mediaItems = Media::all();
        if ($mediaItems->isEmpty()) {
            $this->info('No CMS media records found.');
            return 0;
        }

        $rows = [];
        $exposedCount = 0;
        $missingCount = 0;
        $okCount = 0;

        foreach ($mediaItems as $media) {
            $audience = $media->audience ?? 'both';
            $isRestricted = in_array($audience, ['brothers', 'sisters', 'male', 'female'], true);

            $path = $media->filepath;
            $existsOnPublic = $path ? Storage::disk('public')->exists($path) : false;
            $existsOnLocal = $path ? Storage::disk('local')->exists($path) : false;

            $status = 'OK';
            $action = 'None';

            if ($isRestricted) {
                if ($existsOnPublic) {
                    $status = 'EXPOSED_PUBLIC';
                    $action = 'Relocate to local private disk';
                    $exposedCount++;

                    if (!$isDryRun) {
                        try {
                            $mediaService->update($media, null, ['audience' => $audience]);
                            $action = 'REMEDIATED (Moved to private)';
                        } catch (\Throwable $e) {
                            $action = 'FAILED: ' . $e->getMessage();
                        }
                    }
                } elseif ($existsOnLocal) {
                    $status = 'PROTECTED_PRIVATE';
                    $action = 'None';
                    $okCount++;
                } else {
                    $status = 'MISSING_FILE';
                    $action = 'Verify backup';
                    $missingCount++;
                }
            } else {
                if ($existsOnPublic) {
                    $status = 'PUBLIC_OK';
                    $action = 'None';
                    $okCount++;
                } elseif ($existsOnLocal) {
                    $status = 'ON_LOCAL_DISK';
                    $action = 'Relocate to public disk';
                    if (!$isDryRun) {
                        try {
                            $mediaService->update($media, null, ['audience' => 'both']);
                            $action = 'REMEDIATED (Moved to public)';
                        } catch (\Throwable $e) {
                            $action = 'FAILED: ' . $e->getMessage();
                        }
                    }
                } else {
                    $status = 'MISSING_FILE';
                    $action = 'Verify backup';
                    $missingCount++;
                }
            }

            $rows[] = [
                'uuid' => substr((string) $media->uuid, 0, 8) . '...',
                'name' => substr((string) $media->resolvedDisplayName(), 0, 24),
                'audience' => $audience,
                'path' => substr((string) $path, 0, 30),
                'status' => $status,
                'action' => $action,
            ];
        }

        $this->table(['UUID', 'Name', 'Audience', 'Filepath', 'Status', 'Recommended Action'], $rows);

        $this->newLine();
        $this->info("Summary: {$mediaItems->count()} total items audited.");
        $this->line(" - Compliant items: {$okCount}");
        if ($exposedCount > 0) {
            $this->error(" - Exposed restricted items in public storage: {$exposedCount}");
        }
        if ($missingCount > 0) {
            $this->warn(" - Items with missing storage files: {$missingCount}");
        }

        if ($isDryRun && $exposedCount > 0) {
            $this->warn('To remediate exposed items, run: php artisan cms:audit-media-storage --fix');
        }

        return $exposedCount > 0 && $isDryRun ? 1 : 0;
    }
}
