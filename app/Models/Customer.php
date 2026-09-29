<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'phone', 'email', 'address', 'due_balance'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
