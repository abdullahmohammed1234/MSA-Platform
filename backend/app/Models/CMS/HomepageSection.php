<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'display_order',
        'is_visible',
        'status',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function blocks()
    {
        return $this->hasMany(HomepageContentBlock::class, 'homepage_section_id')->orderBy('display_order');
    }
}
