<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaaSPlan extends Model
{
    use HasFactory;

    protected $table = 'saas_plans';

    protected $fillable = [
        'name',
        'code',
        'monthly_price',
        'max_stores',
        'max_users',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];
}
