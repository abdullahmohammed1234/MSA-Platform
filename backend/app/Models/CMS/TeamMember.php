<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'role',
        'dept',
        'img',
        'bio',
        'email',
        'linkedin',
        'display_order',
        'status',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function revisions()
    {
        return $this->morphMany(CmsRevision::class, 'revisable');
    }
}
