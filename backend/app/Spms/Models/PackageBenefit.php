<?php

namespace App\Spms\Models;

use App\Spms\Enums\DeliverableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageBenefit extends Model
{
    use HasFactory;

    protected $table = 'spms_package_benefits';

    protected $fillable = [
        'package_id',
        'title',
        'description',
        'deliverable_type',
        'quantity',
    ];

    protected $casts = [
        'deliverable_type' => DeliverableType::class,
        'quantity' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
