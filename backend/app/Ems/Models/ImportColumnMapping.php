<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportColumnMapping extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_import_column_mappings';

    protected $fillable = [
        'uuid',
        'name',
        'user_id',
        'event_id',
        'mapping',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
