<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Supplier extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'contact_person',
        'phone',
        'email',
        'due_balance',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
