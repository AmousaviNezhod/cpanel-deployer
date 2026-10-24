<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';

use Panel\Security;

function assertCondition(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("FAILED: $msg");
    }
    echo "  [PASS] $msg\n";
}

echo "=== Running Security Tests ===\n";

// 1. Slug Validation - Valid cases
$validSlugs = ['site1', 'my-site', 'blog_2026', 'portfolio-v2', 'shop_online-1'];
foreach ($validSlugs as $slug) {
    $res = Security::validateSlug($slug, 'panel-secret');
    assertCondition($res['valid'], "Slug '$slug' should be valid");
}

// 2. Slug Validation - Invalid cases
$invalidSlugs = [
    '' => 'empty',
    'a' => 'too short (1 char)',
    'MySite' => 'uppercase',
    '-site' => 'starts with dash',
    '_site' => 'starts with underscore',
    'site name' => 'spaces',
    'site/path' => 'contains slash',
    'site\\path' => 'contains backslash',
    '../evil' => 'path traversal',
    'site..slug' => 'contains double dot',
    'site@domain' => 'special character @',
    'site$dollar' => 'special character $',
    str_repeat('a', 45) => 'too long'
];

foreach ($invalidSlugs as $slug => $reason) {
    $res = Security::validateSlug($slug, 'panel-secret');
    assertCondition(!$res['valid'], "Slug '$slug' should be rejected ($reason)");
}

// 3. Reserved Slugs
$reservedSlugs = ['panel_core', 'panel_data', 'install.php', 'api', 'admin', 'panel-secret'];
foreach ($reservedSlugs as $slug) {
    $res = Security::validateSlug($slug, 'panel-secret');
    assertCondition(!$res['valid'], "Reserved slug '$slug' must be rejected");
}

// 4. Path Containment (isPathWithin)
$baseDir = __DIR__;
assertCondition(Security::isPathWithin(__DIR__ . '/test_security.php', $baseDir), "Child file must be inside baseDir");
assertCondition(!Security::isPathWithin(dirname(__DIR__, 2) . '/evil.txt', $baseDir), "Parent path must be rejected");

// 5. Text Sanitization
$dirty = "<script>alert(1)</script>سلام دنیا \x00\x1F";
$clean = Security::sanitizeText($dirty);
assertCondition(!str_contains($clean, '<script>'), "Tags must be stripped");
assertCondition(str_contains($clean, 'سلام دنیا'), "Valid text must be preserved");

echo "=== Security Tests Passed ===\n\n";
