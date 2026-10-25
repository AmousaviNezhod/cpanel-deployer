<?php
declare(strict_types=1);

echo "========================================================\n";
echo "    cPanel Deployer - Automated Test Suite Runner       \n";
echo "========================================================\n\n";

$tests = [
    'test_security.php',
    'test_auth.php',
    'test_deployer.php',
    'test_fix_routes.php',
    'test_file_manager.php',
    'test_github_deployer.php',
    'test_primary_site.php',
    'test_database_and_disk.php',
    'test_http_flow.php',
];

$failed = false;

foreach ($tests as $testFile) {
    echo ">> Executing: $testFile\n";
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/' . $testFile);
    passthru($cmd, $returnVar);
    if ($returnVar !== 0) {
        echo "FAILED: $testFile returned exit code $returnVar\n";
        $failed = true;
        break;
    }
}

if ($failed) {
    echo "\n[ERROR] One or more test suites failed.\n";
    exit(1);
} else {
    echo "\n[SUCCESS] ALL TEST SUITES PASSED COMPLETELY!\n";
    exit(0);
}
