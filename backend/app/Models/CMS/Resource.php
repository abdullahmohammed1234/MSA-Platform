<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'category',
        'icon_name',
        'link',
        'is_external',
        'tags',
        'file',
        'thumbnail',
        'status',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'tags' => 'array',
    ];

    public function revisions()
    {
        return $this->morphMany(CmsRevision::class, 'revisable');
    }
}
