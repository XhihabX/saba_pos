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
        'variant_id',
        'variant_name',
        'product_name',
        'serial_number',
        'quantity',
        'unit_price',
        'cost_price',
        'discount',
        'vat_rate',
        'vat_amount',
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

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
