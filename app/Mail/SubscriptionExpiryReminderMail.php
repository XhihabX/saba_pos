<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $reminderType,
        public int $daysUntilExpiry
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderType) {
            '7_days' => "Reminder: Your subscription for {$this->tenant->name} expires in 7 days",
            '3_days' => "Important: Your subscription for {$this->tenant->name} expires in 3 days",
            '1_day'  => "Urgent: Your subscription for {$this->tenant->name} expires tomorrow",
            default  => "Notice: Your subscription for {$this->tenant->name} expires today",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $tz = config('subscription.timezone', 'Asia/Dhaka');
        $expiryFormatted = $this->tenant->expires_at
            ? $this->tenant->expires_at->setTimezone($tz)->format('d M Y, h:i A') . " ({$tz})"
            : 'N/A';

        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 24px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <span style='background-color: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase;'>Subscription Reminder</span>
                    <h2 style='color: #0f172a; margin: 12px 0 4px 0;'>Renew Your Plan for {$this->tenant->name}</h2>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Keep your POS workstations and store operations running uninterrupted.</p>
                </div>
                <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 24px;'>
                    <table style='width: 100%; border-collapse: collapse; font-size: 13px;'>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b; font-weight: bold;'>Store Outlet:</td>
                            <td style='padding: 6px 0; color: #0f172a; text-align: right; font-weight: bold;'>{$this->tenant->name}</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b; font-weight: bold;'>Expiration Date:</td>
                            <td style='padding: 6px 0; color: #dc2626; text-align: right; font-weight: bold;'>{$expiryFormatted}</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b; font-weight: bold;'>Status:</td>
                            <td style='padding: 6px 0; color: #0f172a; text-align: right;'>{$this->reminderType} remaining</td>
                        </tr>
                    </table>
                </div>
                <p style='font-size: 13px; line-height: 1.6; color: #475569;'>
                    After the expiration date, a {$this->tenant->grace_days}-day grace period will begin. Once the grace period concludes, counter checkouts will be paused until renewal.
                </p>
                <div style='text-align: center; margin-top: 24px;'>
                    <a href='" . url('/merchant/subscription') . "' style='display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 10px; font-size: 13px; font-weight: bold;'>Renew Subscription Now</a>
                </div>
            </div>
            ",
        );
    }
}
