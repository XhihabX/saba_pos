<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

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
