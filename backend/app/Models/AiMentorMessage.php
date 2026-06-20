<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMentorMessage extends Model
{
    protected $fillable = ['session_id', 'role', 'content'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiMentorSession::class, 'session_id');
    }
}
