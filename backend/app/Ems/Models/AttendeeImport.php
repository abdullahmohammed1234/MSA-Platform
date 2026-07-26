<?php

namespace App\Ems\Models;

use App\Ems\Enums\AttendeeImportStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendeeImport extends Model
{
    use HasEmsUuid, SoftDeletes;

    protected $table = 'ems_attendee_imports';

    protected $fillable = [
        'uuid',
        'event_id',
        'imported_by',
        'original_filename',
        'source',
        'status',
        'column_mapping',
        'summary',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendeeImportStatus::class,
            'column_mapping' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'source' => 'excel_csv',
        'status' => AttendeeImportStatus::Pending->value,
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
