<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * Get the threads for the category.
     */
    public function threads(): HasMany
    {
        return $this->hasMany(DiscussionThread::class, 'category_id');
    }
}
