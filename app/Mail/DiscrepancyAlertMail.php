<?php

namespace App\Mail;

use App\Models\DiscrepancyAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DiscrepancyAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DiscrepancyAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Daily Sales Summary Discrepancy Alert - Store #{$this->alert->store_id} ({$this->alert->date})",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; rounded-radius: 12px;'>
                <h2 style='color: #dc2626; margin-top: 0;'>⚠️ Daily Sales Summary Discrepancy Alert</h2>
                <p>A discrepancy was detected in daily sales summary aggregation for your outlet.</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Tenant ID:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->alert->tenant_id}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Store ID:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->alert->store_id}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Date:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->alert->date}</td></tr>
                </table>
                <p>Please log into your merchant dashboard to recalculate and resolve this summary alert.</p>
            </div>
            ",
        );
    }
}
