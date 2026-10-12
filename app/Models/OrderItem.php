<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'original_price',
        'price_overridden',
        'price_override_reason',
        'cost_price',
        'zero_stock_sold',
        'discount',
        'vat_rate',
        'vat_amount',
        'total',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
