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

    // Route name patterns allowed for read-only access (GET/HEAD requests only)
    'read_only_route_patterns' => [
        'reports.*',
        'pdf.*',
        'pos.invoice.pdf',
        'vat.mushak63',
        'merchant.orders',
        'merchant.auditlogs',
        'manager.reports',
        'manager.auditlogs',
        'manager.shifts.z-report',
    ],
];
