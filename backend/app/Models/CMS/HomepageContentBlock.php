<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageContentBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'homepage_section_id',
        'key',
        'value',
        'type',
        'display_order',
    ];

    public function section()
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }
}
