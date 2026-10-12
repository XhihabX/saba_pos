<?php

namespace App\Mail;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FlaggedOrdersThresholdMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public int $flaggedCount,
        public string $date
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Action Required: High Flagged Offline Orders Threshold Exceeded - {$this->store->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #dc2626; margin-top: 0;'>⚠️ Offline Sales Review Threshold Alert</h2>
                <p>The outlet <strong>{$this->store->name}</strong> has recorded <strong>{$this->flaggedCount}</strong> sales flagged for review on <strong>{$this->date}</strong>.</p>
                <p>These orders were submitted via offline queue sync with server-side stock deficits and require managerial inspection and reconciliation.</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Outlet:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->store->name}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Flagged Orders Today:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->flaggedCount}</td></tr>
                    <tr><td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Date:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$this->date}</td></tr>
                </table>
                <p>Please log in to your Manager/Merchant Portal to inspect and resolve these orders.</p>
            </div>
            ",
        );
    }
}
