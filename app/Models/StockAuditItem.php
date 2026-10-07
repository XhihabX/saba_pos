<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function audit()
    {
        return $this->belongsTo(StockAudit::class, 'stock_audit_id');
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
