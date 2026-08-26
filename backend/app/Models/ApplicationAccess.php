<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationAccess extends Model
{
    use HasFactory;

    protected $table = 'application_access';

    protected $fillable = [
        'user_id',
        'application',
        'granted_by',
    ];

    /**
     * Get the user who has this application access.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the administrator who granted this application access.
     */
    public function granter()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
