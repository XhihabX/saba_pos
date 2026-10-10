<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = ['tenant_id', 'name', 'slug', 'image', 'is_active'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
