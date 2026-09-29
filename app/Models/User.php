<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'tenant_id',
        'store_id',
        'phone',
        'is_active',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin' || $this->role === 'merchant') {
            return true;
        }
        $perms = $this->permissions ?? [];
        return in_array($permission, $perms);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }
}

