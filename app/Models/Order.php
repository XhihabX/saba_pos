<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'invoice_no',
        'store_id',
        'customer_id',
        'user_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'cogs',
        'paid_amount',
        'change_return',
        'payment_status',
        'payment_method',
        'notes',
        'late_synced',
        'flagged_for_review',
        'review_reason',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'late_synced' => 'boolean',
        'flagged_for_review' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }
}
