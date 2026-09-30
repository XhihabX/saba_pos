<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Customer extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = ['tenant_id', 'name', 'phone', 'email', 'address', 'due_balance'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
