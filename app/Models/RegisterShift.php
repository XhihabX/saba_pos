<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegisterShift extends Model
{
    use HasFactory;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
