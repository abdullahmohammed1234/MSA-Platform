<?php

namespace App\Models\CMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'revisable_type',
        'revisable_id',
        'user_id',
        'content',
        'version',
    ];

    protected $casts = [
        'content' => 'array',
        'version' => 'integer',
    ];

    public function revisable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
