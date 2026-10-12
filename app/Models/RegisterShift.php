<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read float|null $opening_float
 */
class RegisterShift extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'opening_cash',
        'closing_cash_counted',
        'total_cash_sales',
        'total_card_sales',
        'total_mobile_sales',
        'expected_cash',
        'cash_difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
