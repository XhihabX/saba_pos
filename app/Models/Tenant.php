<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $mfs_webhook_secret
 * @property float|null $total_sales
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'logo_url',
        'plan_name',
        'subscription_status',
        'mrr_amount',
        'expires_at',
        'grace_days',
        'allow_suspended_read_only',
        'payment_method',
        'sender_number',
        'transaction_id',
        'bank_name',
        'account_holder',
        'account_last4',
        'proof_path',
        'currency_symbol',
        'default_tax_rate',
        'receipt_header',
        'receipt_footer',
        'invoice_prefix',
    ];

    protected $casts = [
        'grace_days' => 'integer',
        'allow_suspended_read_only' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function timezone(): string
    {
        return config('subscription.timezone', 'Asia/Dhaka');
    }

    public function currentTime(): \Carbon\Carbon
    {
        return now()->setTimezone($this->timezone());
    }

    public function expiresAtInTz(): ?\Carbon\Carbon
    {
        return $this->expires_at ? \Carbon\Carbon::parse($this->expires_at)->setTimezone($this->timezone()) : null;
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->expiresAtInTz();
        if (! $expiresAt) {
            return false;
        }

        return $this->currentTime()->greaterThanOrEqualTo($expiresAt);
    }

    public function isInGracePeriod(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        $graceDays = (int) ($this->grace_days ?? config('subscription.default_grace_days', 5));
        $graceEndsAt = $this->expiresAtInTz()->copy()->addDays($graceDays);

        return $this->currentTime()->lessThanOrEqualTo($graceEndsAt);
    }

    public function graceDaysRemaining(): int
    {
        $graceDays = (int) ($this->grace_days ?? config('subscription.default_grace_days', 5));
        if (! $this->isExpired()) {
            return $graceDays;
        }

        $graceEndsAt = $this->expiresAtInTz()->copy()->addDays($graceDays);
        $now = $this->currentTime();

        if ($now->greaterThan($graceEndsAt)) {
            return 0;
        }

        return max(0, (int) ceil(($graceEndsAt->getTimestamp() - $now->getTimestamp()) / 86400));
    }

    public function isGraceExpired(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        $graceDays = (int) ($this->grace_days ?? config('subscription.default_grace_days', 5));
        $graceEndsAt = $this->expiresAtInTz()->copy()->addDays($graceDays);

        return $this->currentTime()->greaterThan($graceEndsAt);
    }

    public function isWithinLateSyncWindow(): bool
    {
        if (! $this->isGraceExpired()) {
            return true;
        }

        $graceDays = (int) ($this->grace_days ?? config('subscription.default_grace_days', 5));
        $lateSyncDays = (int) config('subscription.late_sync_days', 7);
        $lateSyncEndsAt = $this->expiresAtInTz()->copy()->addDays($graceDays + $lateSyncDays);

        return $this->currentTime()->lessThanOrEqualTo($lateSyncEndsAt);
    }

    public function isReadOnlyAllowed(): bool
    {
        if ($this->subscription_status === 'suspended') {
            return (bool) $this->allow_suspended_read_only;
        }

        if ($this->subscription_status !== 'active') {
            return false;
        }

        if (! $this->isGraceExpired()) {
            return false;
        }

        $readOnlyDays = (int) config('subscription.read_only_days', 90);
        $readOnlyEndsAt = $this->expiresAtInTz()->copy()->addDays($readOnlyDays);

        return $this->currentTime()->lessThanOrEqualTo($readOnlyEndsAt);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
