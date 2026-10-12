<?php

/**
 * CI Code Coverage Quality Gate Checker
 *
 * Enforces >= 60% line coverage for all classes handling:
 * - Money: PosController, ExpenseController, MfsWebhookController, EfdBridgeService, ReportController
 * - Stock: StockAdjustmentController, StockAuditController, StockTransferController, ProductController
 * - Auth: AuthController, TwoFactorController, TwoFactorService, EnsureActiveSubscription, EnsureStoreManager, EnsureMerchant, EnsureSuperAdmin, ThrottleFailedPinAttempts
 * - Returns: ReturnController
 * - Shift: ShiftController
 *
 * Usage: php scripts/check_coverage.php [path-to-clover.xml] [min-coverage-percent]
 */

$cloverPath = $argv[1] ?? 'coverage.clover';
$minCoverage = isset($argv[2]) ? (float) $argv[2] : 60.0;

echo "===============================================================\n";
echo "   SABA POS / IOT POS - CORE DOMAIN CODE COVERAGE AUDIT        \n";
echo "===============================================================\n";
echo "Clover XML Report: {$cloverPath}\n";
echo "Required Minimum Line Coverage: {$minCoverage}%\n\n";

if (!file_exists($cloverPath)) {
    echo "❌ ERROR: Clover XML coverage file not found at: {$cloverPath}\n";
    echo "Make sure PHPUnit was executed with --coverage-clover={$cloverPath}\n";
    exit(1);
}

$xmlContent = file_get_contents($cloverPath);
if (empty($xmlContent)) {
    echo "❌ ERROR: Coverage file {$cloverPath} is empty.\n";
    exit(1);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlContent);
if (!$xml) {
    echo "❌ ERROR: Failed to parse XML from {$cloverPath}\n";
    foreach (libxml_get_errors() as $error) {
        echo "  - {$error->message}\n";
    }
    exit(1);
}

// Target Domain Classes
$trackedDomains = [
    'Money' => [
        'App\Http\Controllers\PosController',
        'App\Http\Controllers\ExpenseController',
        'App\Http\Controllers\Api\MfsWebhookController',
        'App\Services\EfdBridgeService',
        'App\Http\Controllers\ReportController',
    ],
    'Stock' => [
        'App\Http\Controllers\StockAdjustmentController',
        'App\Http\Controllers\StockAuditController',
        'App\Http\Controllers\StockTransferController',
        'App\Http\Controllers\ProductController',
    ],
    'Auth' => [
        'App\Http\Controllers\AuthController',
        'App\Http\Controllers\TwoFactorController',
        'App\Services\TwoFactorService',
        'App\Http\Middleware\EnsureActiveSubscription',
        'App\Http\Middleware\EnsureStoreManager',
        'App\Http\Middleware\EnsureMerchant',
        'App\Http\Middleware\EnsureSuperAdmin',
        'App\Http\Middleware\ThrottleFailedPinAttempts',
    ],
    'Returns' => [
        'App\Http\Controllers\ReturnController',
    ],
    'Shift' => [
        'App\Http\Controllers\ShiftController',
    ],
];

// Extract all class coverage from Clover XML
$classCoverage = [];

// Method 1: Scan <class> nodes
$classes = $xml->xpath('//class');
foreach ($classes as $classNode) {
    $className = (string) $classNode['name'];
    $namespace = (string) ($classNode['namespace'] ?? '');
    
    // Normalize fully qualified class name
    $fqcn = $className;
    if ($namespace && !str_starts_with($className, $namespace)) {
        $fqcn = rtrim($namespace, '\\') . '\\' . ltrim($className, '\\');
    }
    $fqcn = ltrim($fqcn, '\\');

    $metrics = $classNode->metrics;
    if ($metrics) {
        $statements = (int) ($metrics['statements'] ?? 0);
        $coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);
        $elements = (int) ($metrics['elements'] ?? 0);
        $coveredElements = (int) ($metrics['coveredelements'] ?? 0);

        $lineCoveragePercent = $statements > 0 
            ? round(($coveredStatements / $statements) * 100, 2) 
            : ($elements > 0 ? round(($coveredElements / $elements) * 100, 2) : 100.0);

        $classCoverage[$fqcn] = [
            'statements' => $statements,
            'covered' => $coveredStatements,
            'percent' => $lineCoveragePercent,
        ];
    }
}

// Method 2: Fallback scan <file> nodes and match relative file path
$files = $xml->xpath('//file');
foreach ($files as $fileNode) {
    $filePath = str_replace('\\', '/', (string) $fileNode['name']);
    $fileMetrics = $fileNode->metrics;
    
    // Check if any tracked class corresponds to this file path
    foreach ($trackedDomains as $domain => $domainClasses) {
        foreach ($domainClasses as $targetFqcn) {
            $expectedSubpath = str_replace('\\', '/', $targetFqcn) . '.php';
            // e.g. App/Http/Controllers/PosController.php or app/Http/Controllers/PosController.php
            if (stripos($filePath, str_replace('App/', 'app/', $expectedSubpath)) !== false
                || stripos($filePath, $expectedSubpath) !== false) {
                
                // If not already populated or if class metrics were missing statements
                if (!isset($classCoverage[$targetFqcn]) || $classCoverage[$targetFqcn]['statements'] === 0) {
                    $stmts = (int) ($fileMetrics['statements'] ?? 0);
                    $covStmts = (int) ($fileMetrics['coveredstatements'] ?? 0);
                    $pct = $stmts > 0 ? round(($covStmts / $stmts) * 100, 2) : 100.0;

                    $classCoverage[$targetFqcn] = [
                        'statements' => $stmts,
                        'covered' => $covStmts,
                        'percent' => $pct,
                    ];
                }
            }
        }
    }
}

// Print and Evaluate Results
printf("%-10s | %-48s | %-12s | %-10s | %-8s\n", "Domain", "Class", "Statements", "Coverage %", "Status");
echo str_repeat("-", 95) . "\n";

$failedClasses = [];
$totalTracked = 0;
$passedTracked = 0;

foreach ($trackedDomains as $domain => $domainClasses) {
    foreach ($domainClasses as $classFqcn) {
        $totalTracked++;
        $shortName = substr($classFqcn, strrpos($classFqcn, '\\') + 1);
        
        if (isset($classCoverage[$classFqcn])) {
            $cov = $classCoverage[$classFqcn];
            $pct = $cov['percent'];
            $stmtStr = "{$cov['covered']}/{$cov['statements']}";
            
            if ($pct >= $minCoverage) {
                $status = "PASS";
                $passedTracked++;
                printf("%-10s | %-48s | %-12s | %8.2f%%  | ✅ %s\n", $domain, $classFqcn, $stmtStr, $pct, $status);
            } else {
                $status = "FAIL";
                $failedClasses[] = [
                    'domain' => $domain,
                    'class' => $classFqcn,
                    'coverage' => $pct,
                    'statements' => $stmtStr,
                    'reason' => "Coverage {$pct}% is below required threshold {$minCoverage}%",
                ];
                printf("%-10s | %-48s | %-12s | %8.2f%%  | ❌ %s\n", $domain, $classFqcn, $stmtStr, $pct, $status);
            }
        } else {
            $failedClasses[] = [
                'domain' => $domain,
                'class' => $classFqcn,
                'coverage' => 0.0,
                'statements' => 'N/A',
                'reason' => "Class not found in coverage clover report",
            ];
            printf("%-10s | %-48s | %-12s | %8.2f%%  | ❌ MISSING\n", $domain, $classFqcn, "0/0", 0.0);
        }
    }
}

echo str_repeat("=", 95) . "\n";
echo "SUMMARY: {$passedTracked} / {$totalTracked} core domain classes satisfy >= {$minCoverage}% line coverage.\n";

if (!empty($failedClasses)) {
    echo "\n❌ COVERAGE GATE FAILED: The following classes did not meet the {$minCoverage}% threshold:\n";
    foreach ($failedClasses as $fail) {
        echo "  - [{$fail['domain']}] {$fail['class']}: {$fail['coverage']}% ({$fail['statements']}) - {$fail['reason']}\n";
    }
    exit(1);
}

echo "✅ ALL CORE DOMAIN CLASSES PASSED (>= {$minCoverage}% Line Coverage)\n";
exit(0);
