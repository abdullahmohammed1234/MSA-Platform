<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'slug', 'description'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->uuid)) {
                $role->uuid = (string) Str::uuid();
            }
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
