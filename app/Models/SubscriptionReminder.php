<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionReminder extends Model
{
    protected $fillable = [
        'tenant_id',
        'expires_at',
        'reminder_type',
        'sent_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
