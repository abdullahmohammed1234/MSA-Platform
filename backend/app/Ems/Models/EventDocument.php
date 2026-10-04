<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EMS Event Document model.
 *
 * Stores metadata for supplementary event PDF documents (e.g. itineraries,
 * menus, maps). The actual file is stored in private storage and exposed via
 * a high-entropy access token URL and QR code.
 *
 * @property int $id
 * @property int $event_id
 * @property string $uuid
 * @property string $name
 * @property string $document_type
 * @property string|null $description
 * @property string $original_filename
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $access_token_hash
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class EventDocument extends Model
{
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'event_documents';

    protected $fillable = [
        'event_id',
        'uuid',
        'name',
        'document_type',
        'description',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'access_token_hash',
        'is_active',
        'sort_order',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    /**
     * The event this document belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * The administrator who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
