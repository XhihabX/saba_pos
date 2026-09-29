<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'customer_id',
        'quotation_no',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'grand_total',
        'items_json',
        'valid_until',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
