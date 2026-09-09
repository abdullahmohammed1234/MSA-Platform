<?php

namespace App\Models\CMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeaturedOpportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'featured_opportunities';

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'eyebrow',
        'short_description',
        'description',
        'featured_image',
        'external_url',
        'features',
        'is_published',
        'published_at',
        'sort_order',
        'author_id',
    ];

    protected $casts = [
        'features' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisions()
    {
        return $this->morphMany(CmsRevision::class, 'revisable');
    }
}
