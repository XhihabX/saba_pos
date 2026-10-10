<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscrepancyAlert extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'date',
        'expected',
        'actual',
        'resolved_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'store_id' => 'integer',
        'date' => 'date:Y-m-d',
        'expected' => 'array',
        'actual' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
