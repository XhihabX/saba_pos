<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Expiry & Lifecycle Configuration
    |--------------------------------------------------------------------------
    */

    'timezone' => 'Asia/Dhaka',

    // Default grace period in days between expires_at and hard cutoff
    'default_grace_days' => 5,

    // Number of days after expiration for which read-only access to reports/invoices is maintained
    'read_only_days' => 90,

    // Grace window in days for accepting late-synced offline sales after the end of grace period
    'late_sync_days' => 7,
];
