<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\SubscriptionReminder;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionReminderCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_reminder_command_sends_idempotently_and_running_twice_sends_once(): void
    {
        Mail::fake();

        $tz = 'Asia/Dhaka';
        $now = Carbon::create(2026, 10, 11, 10, 0, 0, $tz);
        Carbon::setTestNow($now);

        // Tenant expiring in 7 days
        $tenant7Days = Tenant::create([
            'name' => 'Seven Days Store',
            'code' => 'TEN-7D',
            'email' => 'store7d@example.com',
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->addDays(7)->setTime(23, 59, 59),
            'grace_days' => 5,
        ]);

        $owner7Days = User::create([
            'tenant_id' => $tenant7Days->id,
            'name' => 'Owner Seven',
            'email' => 'owner7@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        // First run: Should send exactly 1 reminder
        $exitCodeFirst = Artisan::call('pos:send-subscription-expiry-reminders', [
            '--tenant' => $tenant7Days->id,
        ]);
        $this->assertSame(0, $exitCodeFirst);

        Mail::assertSent(SubscriptionExpiryReminderMail::class, 1);
        Mail::assertSent(SubscriptionExpiryReminderMail::class, function ($mail) use ($owner7Days) {
            return $mail->hasTo($owner7Days->email) && $mail->reminderType === '7_days';
        });

        // Verify database audit record in subscription_reminders
        $this->assertDatabaseHas('subscription_reminders', [
            'tenant_id' => $tenant7Days->id,
            'reminder_type' => '7_days',
        ]);

        // Second run: Must be idempotent and NOT send another email!
        $exitCodeSecond = Artisan::call('pos:send-subscription-expiry-reminders', [
            '--tenant' => $tenant7Days->id,
        ]);
        $this->assertSame(0, $exitCodeSecond);

        // Mail count remains exactly 1!
        Mail::assertSent(SubscriptionExpiryReminderMail::class, 1);

        Carbon::setTestNow();
    }

    public function test_reminder_thresholds_for_3_days_1_day_and_expiry_day(): void
    {
        Mail::fake();

        $tz = 'Asia/Dhaka';
        $now = Carbon::create(2026, 10, 11, 12, 0, 0, $tz);
        Carbon::setTestNow($now);

        // 3 days
        $t3 = Tenant::create([
            'name' => 'Three Days Store',
            'code' => 'TEN-3D',
            'email' => 'store3d@example.com',
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->addDays(3)->setTime(23, 59, 59),
        ]);
        User::create([
            'tenant_id' => $t3->id,
            'name' => 'Owner 3',
            'email' => 'owner3@example.com',
            'password' => bcrypt('secret'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        // 1 day
        $t1 = Tenant::create([
            'name' => 'One Day Store',
            'code' => 'TEN-1D',
            'email' => 'store1d@example.com',
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->addDays(1)->setTime(23, 59, 59),
        ]);
        User::create([
            'tenant_id' => $t1->id,
            'name' => 'Owner 1',
            'email' => 'owner1@example.com',
            'password' => bcrypt('secret'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        // 0 days (today is expiry day)
        $t0 = Tenant::create([
            'name' => 'Today Expiry Store',
            'code' => 'TEN-0D',
            'email' => 'store0d@example.com',
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->setTime(23, 59, 59),
        ]);
        User::create([
            'tenant_id' => $t0->id,
            'name' => 'Owner 0',
            'email' => 'owner0@example.com',
            'password' => bcrypt('secret'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        // Non-threshold tenant (e.g. 5 days) should receive nothing
        $t5 = Tenant::create([
            'name' => 'Five Days Store',
            'code' => 'TEN-5D',
            'email' => 'store5d@example.com',
            'subscription_status' => 'active',
            'expires_at' => $now->copy()->addDays(5)->setTime(23, 59, 59),
        ]);
        User::create([
            'tenant_id' => $t5->id,
            'name' => 'Owner 5',
            'email' => 'owner5@example.com',
            'password' => bcrypt('secret'),
            'role' => 'merchant',
            'is_approved' => true,
        ]);

        Artisan::call('pos:send-subscription-expiry-reminders');

        Mail::assertSent(SubscriptionExpiryReminderMail::class, 3);
        Mail::assertSent(SubscriptionExpiryReminderMail::class, fn ($m) => $m->reminderType === '3_days' && $m->hasTo('owner3@example.com'));
        Mail::assertSent(SubscriptionExpiryReminderMail::class, fn ($m) => $m->reminderType === '1_day' && $m->hasTo('owner1@example.com'));
        Mail::assertSent(SubscriptionExpiryReminderMail::class, fn ($m) => $m->reminderType === '0_days' && $m->hasTo('owner0@example.com'));
        Mail::assertNotSent(SubscriptionExpiryReminderMail::class, fn ($m) => $m->hasTo('owner5@example.com'));

        Carbon::setTestNow();
    }
}
