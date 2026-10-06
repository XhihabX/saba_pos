<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenantable;

class Customer extends Model
{
    use HasFactory, Tenantable, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'phone', 'email', 'address', 'due_balance', 'credit_limit', 'points'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
