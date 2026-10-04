<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'logo_url',
        'plan_name',
        'subscription_status',
        'mrr_amount',
        'expires_at',
        'payment_method',
        'sender_number',
        'transaction_id',
        'currency_symbol',
        'default_tax_rate',
        'receipt_header',
        'receipt_footer',
        'invoice_prefix',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
