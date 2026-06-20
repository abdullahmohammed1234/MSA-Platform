<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'slug', 'description', 'module'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->uuid)) {
                $permission->uuid = (string) Str::uuid();
            }
        });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
