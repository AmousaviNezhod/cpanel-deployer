<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Auth.php';

use Panel\Auth;

function assertConditionAuth(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("FAILED: $msg");
    }
    echo "  [PASS] $msg\n";
}

echo "=== Running Auth Tests ===\n";

$testDataDir = __DIR__ . '/tmp_auth_test_' . uniqid();
@mkdir($testDataDir, 0755, true);

try {
    // 1. Password Verification
    $password = 'SecretPass123!@#';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    assertConditionAuth(password_verify($password, $hash), "Valid password verifies correctly");
    assertConditionAuth(!password_verify('WrongPass', $hash), "Wrong password fails verification");

    // 2. CSRF Token
    $token1 = Auth::getCsrfToken();
    assertConditionAuth(is_string($token1) && strlen($token1) === 64, "CSRF token generated (64 hex chars)");
    assertConditionAuth(Auth::validateCsrfToken($token1), "CSRF token validates correctly");
    assertConditionAuth(!Auth::validateCsrfToken('invalid_token'), "Invalid CSRF token fails validation");

    // 3. Rate Limiting Logic
    $rateInit = Auth::checkRateLimit($testDataDir);
    assertConditionAuth($rateInit['allowed'], "Initial check should be allowed");
    assertConditionAuth($rateInit['remaining'] === 5, "Initial remaining attempts should be 5");

    // Fail 4 times
    for ($i = 0; $i < 4; $i++) {
        Auth::recordFailedLogin($testDataDir);
    }

    $rateAfter4 = Auth::checkRateLimit($testDataDir);
    assertConditionAuth($rateAfter4['allowed'], "4 failures should still be allowed");
    assertConditionAuth($rateAfter4['remaining'] === 1, "Remaining attempts after 4 failures should be 1");

    // 5th failure -> lockout
    Auth::recordFailedLogin($testDataDir);
    $rateAfter5 = Auth::checkRateLimit($testDataDir);
    assertConditionAuth(!$rateAfter5['allowed'], "5th failure must trigger lockout");
    assertConditionAuth($rateAfter5['retry_after'] > 0, "Lockout must report retry_after > 0");

    // Reset rate limit
    Auth::resetRateLimit($testDataDir);
    $rateAfterReset = Auth::checkRateLimit($testDataDir);
    assertConditionAuth($rateAfterReset['allowed'], "After reset, check should be allowed");
    assertConditionAuth($rateAfterReset['remaining'] === 5, "After reset, remaining attempts should be 5");

    echo "=== Auth Tests Passed ===\n\n";
} finally {
    // Cleanup
    $rateFile = $testDataDir . '/rate_limits.json';
    if (file_exists($rateFile)) {
        @unlink($rateFile);
    }
    @rmdir($testDataDir);
}
