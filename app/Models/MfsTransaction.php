<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MfsTransaction extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'trx_id',
        'sender',
        'amount',
        'gateway',
        'status',
        'order_id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
