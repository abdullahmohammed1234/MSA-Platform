<?php

namespace App\Models\CMS;

use App\Models\User;
use App\Support\CmsAssetUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'filename',
        'display_name',
        'category_id',
        'filepath',
        'url',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $hidden = [
        'filepath',
    ];

    protected $appends = [
        'media_type',
    ];

    public function getUrlAttribute($value): ?string
    {
        if (!empty($this->attributes['filepath'])) {
            return Storage::disk('public')->url($this->attributes['filepath']);
        }

        return CmsAssetUrl::resolve($value);
    }

    public function getMediaTypeAttribute(): string
    {
        $mime = strtolower((string) ($this->attributes['mime_type'] ?? ''));

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        $extension = strtolower((string) pathinfo((string) ($this->attributes['filename'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'], true)) {
            return 'image';
        }
        if (in_array($extension, ['mp4', 'webm', 'mov', 'ogv', 'ogg'], true)) {
            return 'video';
        }

        return 'document';
    }

    /**
     * Prefer custom display name; fall back to original filename.
     */
    public function resolvedDisplayName(): string
    {
        $custom = trim((string) ($this->display_name ?? ''));

        return $custom !== '' ? $custom : (string) $this->filename;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class, 'category_id');
    }
}
