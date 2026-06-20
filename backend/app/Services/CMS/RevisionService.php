<?php

namespace App\Services\CMS;

use App\Models\CMS\CmsRevision;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class RevisionService
{
    /**
     * Create a revision for an editable CMS model.
     */
    public function createRevision(Model $model, ?int $userId): CmsRevision
    {
        // Get serializable attributes (excluding system fields)
        $content = $model->toArray();
        unset($content['id'], $content['created_at'], $content['updated_at'], $content['deleted_at']);

        // Determine current version number
        $latestVersion = CmsRevision::where('revisable_type', get_class($model))
            ->where('revisable_id', $model->id)
            ->max('version') ?? 0;

        $version = $latestVersion + 1;

        return CmsRevision::create([
            'revisable_type' => get_class($model),
            'revisable_id' => $model->id,
            'user_id' => $userId,
            'content' => $content,
            'version' => $version,
        ]);
    }

    /**
     * Rollback a CMS model to a specific version.
     */
    public function rollback(Model $model, int $version, ?int $userId): bool
    {
        $revision = CmsRevision::where('revisable_type', get_class($model))
            ->where('revisable_id', $model->id)
            ->where('version', $version)
            ->first();

        if (!$revision) {
            return false;
        }

        // Restore attributes
        $model->update($revision->content);

        // Record rollback action in audit log
        $this->logAction($userId, 'rollback', $model, "Rolled back to version {$version}");

        // Create a new revision representing this rollback state
        $this->createRevision($model, $userId);

        return true;
    }

    /**
     * Log a CMS action in the database.
     */
    public function logAction(?int $userId, string $action, ?Model $model, string $description, ?array $payload = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $model ? get_class($model) : null,
            'target_id' => $model ? $model->id : null,
            'description' => $description,
            'payload' => $payload,
        ]);
    }

    /**
     * Get revision history for a model.
     */
    public function getRevisions(Model $model)
    {
        return CmsRevision::with('user:id,name')
            ->where('revisable_type', get_class($model))
            ->where('revisable_id', $model->id)
            ->orderBy('version', 'desc')
            ->get();
    }
}
