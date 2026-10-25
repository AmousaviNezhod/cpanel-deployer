<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/Deployer.php';

echo "=== Running Subpath Route Adapter Tests ===\n";

$tmpDir = __DIR__ . '/tmp_route_test_' . uniqid();
$tmpPublic = $tmpDir . '/public_html';
$tmpData = $tmpDir . '/data';
mkdir($tmpPublic, 0755, true);
mkdir($tmpData, 0755, true);

// Create sample ZIP with root-relative paths
$sampleDir = $tmpDir . '/site_src';
mkdir($sampleDir . '/css', 0755, true);
mkdir($sampleDir . '/js', 0755, true);

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Shoes Store</title>
    <link rel="stylesheet" href="/css/style.css">
    <script src="/js/app.js"></script>
</head>
<body>
    <img src="/images/banner.png">
    <a href="/checkout">Checkout</a>
    <a href="https://google.com">Google</a>
</body>
</html>
HTML;
file_put_contents($sampleDir . '/index.html', $html);

$css = <<<CSS
body { background: url(/images/bg.png); }
.icon { background: url('/images/icon.svg'); }
CSS;
file_put_contents($sampleDir . '/css/style.css', $css);

$htaccess = <<<HTA
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ index.php [L]
</IfModule>
HTA;
file_put_contents($sampleDir . '/.htaccess', $htaccess);

// Zip it
$zipFile = $tmpDir . '/sample.zip';
$zip = new ZipArchive();
$zip->open($zipFile, ZipArchive::CREATE);
$zip->addFile($sampleDir . '/index.html', 'index.html');
$zip->addFile($sampleDir . '/css/style.css', 'css/style.css');
$zip->addFile($sampleDir . '/.htaccess', '.htaccess');
$zip->close();

$deployer = new \Panel\Deployer($tmpPublic, $tmpData);
$fileInfo = [
    'name' => 'sample.zip',
    'type' => 'application/zip',
    'tmp_name' => $zipFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($zipFile)
];

// Test 1: Deploy with fixRoutes = true
$res = $deployer->deploy($fileInfo, 'sportshoes', false, true);
assert($res['success'] === true, 'Deployment with route adaptation must succeed');

$deployedHtml = file_get_contents($tmpPublic . '/sportshoes/index.html');
assert(str_contains($deployedHtml, '<base href="/sportshoes/">'), 'Must inject base href');
assert(str_contains($deployedHtml, 'href="/sportshoes/css/style.css"'), 'Must adapt css link');
assert(str_contains($deployedHtml, 'src="/sportshoes/js/app.js"'), 'Must adapt js src');
assert(str_contains($deployedHtml, 'src="/sportshoes/images/banner.png"'), 'Must adapt img src');
assert(str_contains($deployedHtml, 'href="/sportshoes/checkout"'), 'Must adapt checkout link');
assert(str_contains($deployedHtml, 'href="https://google.com"'), 'Must keep external url unchanged');
echo "  [PASS] HTML base href injection & root attribute adaptation succeeded\n";

$deployedCss = file_get_contents($tmpPublic . '/sportshoes/css/style.css');
assert(str_contains($deployedCss, 'url(/sportshoes/images/bg.png)'), 'Must adapt CSS url');
assert(str_contains($deployedCss, "url('/sportshoes/images/icon.svg')"), 'Must adapt quoted CSS url');
echo "  [PASS] CSS root-relative url adaptation succeeded\n";

$deployedHtaccess = file_get_contents($tmpPublic . '/sportshoes/.htaccess');
assert(str_contains($deployedHtaccess, 'RewriteBase /sportshoes/'), 'Must inject RewriteBase in .htaccess');
echo "  [PASS] .htaccess RewriteBase injection succeeded\n";

// Cleanup
\Panel\Security::deleteDirectory($tmpDir);
echo "=== All Subpath Route Adapter Tests Passed ===\n";
