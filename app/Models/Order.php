<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
