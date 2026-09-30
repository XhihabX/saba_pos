<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = ['tenant_id', 'store_id', 'title', 'category', 'amount', 'date', 'notes'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
