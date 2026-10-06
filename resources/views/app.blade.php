<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <title>IOT POS - International Office Technology Point of Sale & ERP</title>
    <meta name="description" content="International Office Technology (IOT) POS is an enterprise multi-outlet point of sale and cloud ERP system engineered for fast counter transactions, inventory management, and business analytics." />
    <meta name="keywords" content="IOT, International Office Technology, POS, Point of Sale, ERP, Inventory Management, Retail Software, SaaS POS" />
    <meta property="og:title" content="IOT POS - International Office Technology Enterprise Point of Sale & ERP" />
    <meta property="og:description" content="Sub-10ms counter workstation responsiveness, zero-downtime offline sales resilience, and multi-store chain inventory governance by International Office Technology." />
    <meta property="og:type" content="website" />
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
  </head>
  <body class="h-full font-sans antialiased selection:bg-indigo-500 selection:text-white bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    @inertia
  </body>
</html>
