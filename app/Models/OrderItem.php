<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'product_name',
        'serial_number',
        'quantity',
        'unit_price',
        'discount',
        'total',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
