<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenantable;

class Product extends Model
{
    use HasFactory, Tenantable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'barcode',
        'category_id',
        'brand_id',
        'unit_id',
        'purchase_cost',
        'selling_price',
        'vat_rate',
        'vat_mode',
        'alert_quantity',
        'has_serial',
        'image',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}

