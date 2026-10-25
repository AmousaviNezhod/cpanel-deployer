<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';

echo "=== Running Primary / Root Website Tests ===\n";

$tmpDir = __DIR__ . '/tmp_primary_test_' . uniqid();
$tmpPublic = $tmpDir . '/public_html';
$tmpData = $tmpDir . '/panel_data';
mkdir($tmpPublic, 0755, true);
mkdir($tmpData, 0755, true);

// Create fake root .htaccess
$rootHtaccess = $tmpPublic . '/.htaccess';
file_put_contents($rootHtaccess, "# Root .htaccess\nOptions -Indexes\n");

// Create initial config.json
$config = [
    'admin_slug' => 'panel-test',
    'primary_slug' => null
];
file_put_contents($tmpData . '/config.json', json_encode($config));

// Create sample site 'my-home-site'
$siteDir = $tmpPublic . '/my-home-site';
mkdir($siteDir, 0755, true);
mkdir($siteDir . '/pages', 0755, true);
file_put_contents($siteDir . '/index.html', '<!DOCTYPE html><html><head><base href="/my-home-site/"><title>My Site</title></head><body><h1>Welcome</h1></body></html>');
file_put_contents($siteDir . '/pages/about.html', '<!DOCTYPE html><html><head><base href="/my-home-site/pages/"><title>About</title></head><body><h1>About</h1></body></html>');

// Register site in sites.json
$sm = new \Panel\SiteManager($tmpData, $tmpPublic);
$sm->save([
    'name' => 'My Home Site',
    'slug' => 'my-home-site',
    'type' => 'static',
    'index_file' => 'index.html',
    'created_at' => date('Y-m-d H:i:s')
]);

// 1. Initial State: no primary site
assert($sm->getPrimarySite($config) === null, 'Initially primary site must be null');
echo "  [PASS] Initial state has no primary site\n";

// 2. Set as Primary Site
$setRes = $sm->setPrimarySite('my-home-site', $config, $tmpData);
assert($setRes['success'] === true, 'setPrimarySite must succeed');
assert($config['primary_slug'] === 'my-home-site', 'config primary_slug must be my-home-site');

// Verify config.json on disk
$savedConfig = json_decode((string)file_get_contents($tmpData . '/config.json'), true);
assert($savedConfig['primary_slug'] === 'my-home-site', 'Saved config on disk must have primary_slug');

// Verify root .htaccess has PRIMARY_SITE_ROUTING block
$htContent = (string)file_get_contents($rootHtaccess);
assert(strpos($htContent, '# BEGIN PRIMARY_SITE_ROUTING') !== false, 'Root .htaccess must contain PRIMARY_SITE_ROUTING block');
assert(strpos($htContent, 'my-home-site') !== false, 'Root .htaccess must reference my-home-site');
echo "  [PASS] setPrimarySite injected rewrite rules into root .htaccess\n";

// Verify HTML files adapted base href to root
$indexContent = (string)file_get_contents($siteDir . '/index.html');
assert(strpos($indexContent, '<base href="/">') !== false, 'index.html must have base href="/"');

$aboutContent = (string)file_get_contents($siteDir . '/pages/about.html');
assert(strpos($aboutContent, '<base href="/pages/">') !== false, 'about.html must have base href="/pages/"');
echo "  [PASS] HTML base hrefs successfully adapted to root domain\n";

// 3. Test root index.php execution simulation
$indexPhpScript = __DIR__ . '/../index.php';
// Copy index.php to $tmpPublic
copy($indexPhpScript, $tmpPublic . '/index.php');
touch($tmpData . '/install.lock'); // Mark as installed

// Run index.php through CLI to verify it outputs the primary site's HTML
$output = shell_exec("php -d display_errors=1 \"{$tmpPublic}/index.php\"");
assert(strpos($output, '<h1>Welcome</h1>') !== false, 'index.php must serve primary site HTML content');
assert(strpos($output, '<base href="/">') !== false, 'Served HTML must have root base href');
echo "  [PASS] Root index.php transparently rendered primary site content\n";

// 4. Unset Primary Site
$unsetRes = $sm->unsetPrimarySite($config, $tmpData);
assert($unsetRes['success'] === true, 'unsetPrimarySite must succeed');
assert($config['primary_slug'] === null, 'config primary_slug must be null');

// Verify .htaccess block removed
$htClean = (string)file_get_contents($rootHtaccess);
assert(strpos($htClean, '# BEGIN PRIMARY_SITE_ROUTING') === false, 'Root .htaccess must not contain PRIMARY_SITE_ROUTING block');

// Verify HTML base hrefs restored to subpath
$indexRestored = (string)file_get_contents($siteDir . '/index.html');
assert(strpos($indexRestored, '<base href="/my-home-site/">') !== false, 'index.html base href must be restored to /my-home-site/');

$aboutRestored = (string)file_get_contents($siteDir . '/pages/about.html');
assert(strpos($aboutRestored, '<base href="/my-home-site/pages/">') !== false, 'about.html base href must be restored to /my-home-site/pages/');
echo "  [PASS] unsetPrimarySite restored default .htaccess and subpath base hrefs\n";

// Test root index.php again - should now render default landing page
$defaultOutput = shell_exec("php -d display_errors=1 \"{$tmpPublic}/index.php\"");
assert(strpos($defaultOutput, 'سایت در حال اجرا است') !== false, 'index.php must now serve default landing page');
echo "  [PASS] Root index.php reverted to default landing page\n";

// Cleanup
\Panel\Security::deleteDirectory($tmpDir);
echo "=== All Primary / Root Website Tests Passed ===\n";
