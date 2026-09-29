<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkedOrder extends Model
{
    use HasFactory;

    protected $fillable = ['reference_no', 'store_id', 'customer_name', 'cart_data', 'notes'];

    protected $casts = [
        'cart_data' => 'array',
    ];
}
