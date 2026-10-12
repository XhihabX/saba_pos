<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $orders_count
 */
class Store extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $attributes = [
        'default_tax_rate' => 15.00,
        'allow_negative_stock' => false,
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
        'vat_mode',
        'is_vat_registered',
        'mfs_number',
        'sms_gateway_url',
        'sms_api_key',
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
