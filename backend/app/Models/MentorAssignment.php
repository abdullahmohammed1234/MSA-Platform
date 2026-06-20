<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MentorAssignment extends Model
{
    use HasFactory;

    protected $table = 'mentor_assignments';

    protected $fillable = [
        'mentor_id',
        'student_id',
        'assigned_by',
        'status',
        'notes',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
