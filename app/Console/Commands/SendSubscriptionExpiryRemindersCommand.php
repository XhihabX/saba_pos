<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\SubscriptionReminder;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pos:send-subscription-expiry-reminders {--tenant= : Process a specific tenant ID}';

    /**
     * The console command description.
     */
    protected $description = 'Send idempotent subscription expiry reminder emails at 7, 3, 1 days before expiry and on expiry day in Asia/Dhaka';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tz = config('subscription.timezone', 'Asia/Dhaka');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();

        $query = Tenant::query()
            ->where('subscription_status', 'active')
            ->whereNotNull('expires_at');

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($tenants as $tenant) {
            $expiryInTz = Carbon::parse($tenant->expires_at)->setTimezone($tz);
            $expiryDay = $expiryInTz->copy()->startOfDay();

            $daysUntilExpiry = (int) $today->diffInDays($expiryDay, false);

            $reminderType = match ($daysUntilExpiry) {
                7 => '7_days',
                3 => '3_days',
                1 => '1_day',
                0 => '0_days',
                default => null,
            };

            if (! $reminderType) {
                continue;
            }

            // Check idempotency: has this reminder type already been sent for this specific expiry timestamp?
            $alreadySent = SubscriptionReminder::where('tenant_id', $tenant->id)
                ->where('expires_at', $tenant->expires_at)
                ->where('reminder_type', $reminderType)
                ->exists();

            if ($alreadySent) {
                $skippedCount++;
                $this->line("Tenant #{$tenant->id} ({$tenant->name}): {$reminderType} reminder already sent for expiry {$tenant->expires_at}. Skipping.");
                continue;
            }

            // Find store owner (merchant) email
            $owner = $tenant->users()->where('role', 'merchant')->first();
            $recipientEmail = $owner?->email ?? $tenant->email;

            if (empty($recipientEmail)) {
                $this->warn("Tenant #{$tenant->id} ({$tenant->name}): No valid recipient email found. Skipping.");
                continue;
            }

            try {
                // Record in subscription_reminders before or upon sending to guarantee idempotency
                SubscriptionReminder::create([
                    'tenant_id' => $tenant->id,
                    'expires_at' => $tenant->expires_at,
                    'reminder_type' => $reminderType,
                    'sent_at' => Carbon::now($tz),
                ]);

                Mail::to($recipientEmail)->send(new SubscriptionExpiryReminderMail($tenant, $reminderType, $daysUntilExpiry));

                $sentCount++;
                $this->info("Tenant #{$tenant->id} ({$tenant->name}): Sent {$reminderType} reminder to {$recipientEmail}.");
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Concurrently sent by another execution
                $skippedCount++;
            } catch (\Throwable $e) {
                $this->error("Tenant #{$tenant->id} ({$tenant->name}): Failed to send reminder: " . $e->getMessage());
            }
        }

        $this->info("Reminder run complete. Sent: {$sentCount}, Skipped/Idempotent: {$skippedCount}.");

        return Command::SUCCESS;
    }
}
