<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $orders_count
 */
class Store extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $attributes = [
        'default_tax_rate' => 15.00,
        'allow_negative_stock' => false,
        'shelf_pickup_mode' => false,
        'max_counter_verified_amount' => 5000.00,
        'max_counter_verified_per_shift' => 10,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'vat_number',
        'bin_number',
        'currency_symbol',
        'default_tax_rate',
        'receipt_header',
        'receipt_footer',
        'is_active',
        'is_godown',
        'allow_negative_stock',
        'shelf_pickup_mode',
        'vat_mode',
        'is_vat_registered',
        'mfs_number',
        'sms_gateway_url',
        'sms_api_key',
        'max_cashier_discount_percent',
        'max_counter_verified_amount',
        'max_counter_verified_per_shift',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
