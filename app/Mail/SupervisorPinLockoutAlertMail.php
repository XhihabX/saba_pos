<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupervisorPinLockoutAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $storeId,
        public int $failureCount = 5,
        public int $lockoutMinutes = 15
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 Security Alert: Supervisor PIN Lockout Triggered for {$this->user->name} (Store #{$this->storeId})",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #dc2626; margin-top: 0;'>🚨 Security Alert: Supervisor PIN Lockout</h2>
                <p>Repeated failed supervisor PIN attempts were detected at the cashier terminal.</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Cashier / User:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->user->name} ({$this->user->email})</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Store ID:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>#{$this->storeId}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Failed Attempts:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->failureCount} failures in 10 minutes</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Action Taken:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>User locked out for {$this->lockoutMinutes} minutes across all network origins</td></tr>
                </table>
                <p style='color: #64748b; font-size: 13px;'>If this was not authorized staff activity, please review CCTV or terminal access logs immediately.</p>
            </div>
            ",
        );
    }
}
