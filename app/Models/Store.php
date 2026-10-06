<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, Tenantable, SoftDeletes;

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
        'mfs_number',
        'sms_gateway_url',
        'sms_api_key',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

