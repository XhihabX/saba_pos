<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_audit_id',
        'product_id',
        'variant_id',
        'expected_qty',
        'counted_qty',
        'variance_qty',
        'unit_cost',
        'variance_value',
        'notes',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'variance_value' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<StockAudit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'stock_audit_id');
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
