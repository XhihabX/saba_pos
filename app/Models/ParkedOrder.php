<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkedOrder extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = ['tenant_id', 'reference_no', 'store_id', 'customer_name', 'cart_data', 'notes'];

    protected $casts = [
        'cart_data' => 'array',
    ];
}
