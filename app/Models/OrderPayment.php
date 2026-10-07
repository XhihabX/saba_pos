<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = ['tenant_id', 'order_id', 'payment_method', 'amount', 'reference_no'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
