<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/Deployer.php';

use Panel\Security;
use Panel\SiteManager;
use Panel\Deployer;

echo "=== Running Site Download Tests ===\n";

$tmpData = sys_get_temp_dir() . '/test_dl_data_' . bin2hex(random_bytes(4));
$tmpPublic = sys_get_temp_dir() . '/test_dl_public_' . bin2hex(random_bytes(4));

@mkdir($tmpData . '/tmp', 0755, true);
@mkdir($tmpPublic, 0755, true);

// Create a test site
$slug = 'download-test';
$siteDir = $tmpPublic . '/' . $slug;
@mkdir($siteDir . '/assets', 0755, true);
file_put_contents($siteDir . '/index.html', '<h1>Download Test</h1>');
file_put_contents($siteDir . '/assets/app.js', 'console.log("hello");');
file_put_contents($siteDir . '/assets/style.css', 'body { color: red; }');

$siteManager = new SiteManager($tmpData, $tmpPublic);

// Test 1: Export site zip
$zipPath = $siteManager->exportSiteZip($slug);
assert($zipPath !== null, "Export zip path should not be null");
assert(file_exists($zipPath), "Export zip file must exist on disk");
echo "  [PASS] exportSiteZip created valid file at: {$zipPath}\n";

// Test 2: Check zip content
$zip = new \ZipArchive();
$openRes = $zip->open($zipPath);
assert($openRes === true, "ZipArchive should open the exported archive");
assert($zip->locateName('index.html') !== false, "index.html must be inside zip");
assert($zip->locateName('assets/app.js') !== false, "assets/app.js must be inside zip");
assert($zip->locateName('assets/style.css') !== false, "assets/style.css must be inside zip");
$zip->close();
echo "  [PASS] Exported zip contains all site files with correct relative structure\n";

// Test 3: Attempt to export invalid / non-existent slug
$invalidZip = $siteManager->exportSiteZip('../evil');
assert($invalidZip === null, "Path traversal or non-existent slug must return null");
echo "  [PASS] Path traversal slug properly rejected\n";

// Clean up
@unlink($zipPath);
Security::deleteDirectory($tmpData);
Security::deleteDirectory($tmpPublic);

echo "=== All Site Download Tests Passed ===\n";
