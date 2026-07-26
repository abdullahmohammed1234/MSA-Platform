<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_email_templates';

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'category',
        'subject',
        'body_html',
        'body_text',
        'placeholders',
        'is_active',
        'is_system',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
