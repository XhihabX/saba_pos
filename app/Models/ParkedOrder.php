<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\Tenantable;

class ParkedOrder extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = ['tenant_id', 'reference_no', 'store_id', 'customer_name', 'cart_data', 'notes'];

    protected $casts = [
        'cart_data' => 'array',
    ];
}
