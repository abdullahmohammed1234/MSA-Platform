<?php

namespace App\Models\CMS;

use App\Models\User;
use App\Support\CmsAssetUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'filename',
        'filepath',
        'url',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $hidden = [
        'filepath',
    ];

    public function getUrlAttribute($value): ?string
    {
        if (!empty($this->attributes['filepath'])) {
            return Storage::disk('public')->url($this->attributes['filepath']);
        }

        return CmsAssetUrl::resolve($value);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
