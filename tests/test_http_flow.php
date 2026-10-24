<?php
declare(strict_types=1);

/**
 * End-to-End HTTP Integration Test for cPanel Deployer
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/panel_core/Security.php';

$testEnvDir = __DIR__ . '/http_sandbox_' . uniqid();
@mkdir($testEnvDir, 0755, true);

// Helper function to recursively copy files
function copyDir(string $src, string $dst): void {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if ($file !== '.' && $file !== '..') {
            if (is_dir($src . '/' . $file)) {
                copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// Copy source code to sandbox
copy($rootDir . '/install.php', $testEnvDir . '/install.php');
copy($rootDir . '/index.php', $testEnvDir . '/index.php');
copy($rootDir . '/.htaccess', $testEnvDir . '/.htaccess');
copyDir($rootDir . '/panel_core', $testEnvDir . '/panel_core');
copyDir($rootDir . '/panel_data', $testEnvDir . '/panel_data');

// Find an open port using core streams
$sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$sock) {
    throw new RuntimeException("Cannot bind ephemeral port: $errstr");
}
$name = stream_socket_get_name($sock, false);
fclose($sock);
$port = (int)substr($name, strrpos($name, ':') + 1);

$serverHost = "127.0.0.1:$port";

echo "Starting test PHP server on http://$serverHost ...\n";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$cmd = "php -S $serverHost -t " . escapeshellarg($testEnvDir);
$proc = proc_open($cmd, $descriptors, $pipes, $testEnvDir);

if (!is_resource($proc)) {
    throw new RuntimeException("Failed to start PHP test server");
}

$procStatus = proc_get_status($proc);
$serverPid = $procStatus['pid'] ?? null;

// Wait for server to bind
$bound = false;
for ($attempt = 0; $attempt < 15; $attempt++) {
    usleep(150000); // 150ms
    $conn = @fsockopen('127.0.0.1', $port);
    if (is_resource($conn)) {
        fclose($conn);
        $bound = true;
        break;
    }
}
if (!$bound) {
    throw new RuntimeException("Test server failed to bind to port $port");
}

$cookieJar = $testEnvDir . '/cookies.txt';

function httpReq(string $url, string $method = 'GET', $data = null, array $headers = [], ?string $cookieJar = null): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException("cURL error: $curlErr");
    }

    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $body
    ];
}

function assertHttp(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("HTTP TEST FAILED: $msg");
    }
    echo "  [PASS] $msg\n";
}

try {
    echo "\n=== 1. Test Installer GET ===\n";
    $res = httpReq("http://$serverHost/install.php");
    assertHttp($res['code'] === 200, "Installer page returns HTTP 200");
    assertHttp(str_contains($res['body'], 'نصب cPanel Deployer'), "Installer form renders Persian title");

    echo "\n=== 2. Test Installer POST (Complete Setup) ===\n";
    $adminSlug = 'panel-secret-xyz';
    $postData = [
        'admin_slug' => $adminSlug,
        'admin_password' => 'MySecurePass123!@#',
        'admin_password_confirm' => 'MySecurePass123!@#'
    ];
    $res = httpReq("http://$serverHost/install.php", 'POST', $postData);
    assertHttp($res['code'] === 200, "Installer submission returns HTTP 200");
    assertHttp(str_contains($res['body'], 'نصب با موفقیت انجام شد'), "Installation confirmation displayed");
    assertHttp(str_contains($res['body'], $adminSlug), "Generated admin slug shown on success screen");

    echo "\n=== 3. Test Installer Lockout ===\n";
    $res = httpReq("http://$serverHost/install.php");
    assertHttp($res['code'] === 403, "Installer now returns 403 Forbidden");

    echo "\n=== 4. Test Unauthenticated Access to Admin Panel ===\n";
    $res = httpReq("http://$serverHost/$adminSlug/", 'GET', null, [], $cookieJar);
    assertHttp($res['code'] === 200, "Admin URL returns 200 (Login view)");
    assertHttp(str_contains($res['body'], 'ورود به پنل دیپلوی'), "Login view is presented");

    echo "\n=== 5. Test Failed Login Rate Limiting ===\n";
    $res = httpReq("http://$serverHost/$adminSlug/?action=login", 'POST', ['password' => 'WrongPass'], [], $cookieJar);
    assertHttp(str_contains($res['body'], 'اشتباه است') || str_contains($res['body'], 'تلاش باقی‌مانده'), "Failed password warning returned");

    echo "\n=== 6. Test Successful Login & CSRF Extraction ===\n";
    $res = httpReq("http://$serverHost/$adminSlug/?action=login", 'POST', ['password' => 'MySecurePass123!@#'], [], $cookieJar);
    assertHttp(str_contains($res['headers'], 'Location: ./'), "Successful login redirects to dashboard");

    $res = httpReq("http://$serverHost/$adminSlug/", 'GET', null, [], $cookieJar);
    assertHttp(str_contains($res['body'], 'سایت‌های مستقر'), "Dashboard page rendered");
    assertHttp(str_contains($res['body'], 'csrf-token'), "CSRF token meta present in HTML");

    preg_match('/<meta name="csrf-token" content="([a-f0-9]+)">/', $res['body'], $matches);
    $csrfToken = $matches[1] ?? '';
    assertHttp(strlen($csrfToken) === 64, "CSRF token successfully parsed ($csrfToken)");

    echo "\n=== 7. Test Deploying Static Site ===\n";
    $staticZipPath = $rootDir . '/samples/sample-static.zip';
    $postDeployStatic = [
        'csrf_token' => $csrfToken,
        'name' => 'سایت استاتیک تست',
        'slug' => 'test-static',
        'zip_file' => new CURLFile($staticZipPath, 'application/zip', 'sample-static.zip')
    ];
    $res = httpReq("http://$serverHost/$adminSlug/?action=deploy", 'POST', $postDeployStatic, ['X-Requested-With: XMLHttpRequest'], $cookieJar);
    assertHttp($res['code'] === 200, "Deploy static site returns HTTP 200");
    $json = json_decode($res['body'], true);
    assertHttp(!empty($json['success']), "Deploy response indicates success");

    echo "\n=== 8. Test Accessing Deployed Static Site ===\n";
    $res = httpReq("http://$serverHost/test-static/index.html");
    assertHttp($res['code'] === 200, "Static index.html returns HTTP 200");
    assertHttp(str_contains($res['body'], 'سایت استاتیک نمونه'), "Static HTML content rendered");

    $res = httpReq("http://$serverHost/test-static/about.html");
    assertHttp($res['code'] === 200, "Static about.html returns HTTP 200");

    $res = httpReq("http://$serverHost/test-static/css/style.css");
    assertHttp($res['code'] === 200, "Static CSS returns HTTP 200");

    echo "\n=== 9. Test Deploying PHP Site ===\n";
    $phpZipPath = $rootDir . '/samples/sample-php.zip';
    $postDeployPhp = [
        'csrf_token' => $csrfToken,
        'name' => 'اپلیکیشن تست پی‌اچ‌پی',
        'slug' => 'test-php',
        'zip_file' => new CURLFile($phpZipPath, 'application/zip', 'sample-php.zip')
    ];
    $res = httpReq("http://$serverHost/$adminSlug/?action=deploy", 'POST', $postDeployPhp, ['X-Requested-With: XMLHttpRequest'], $cookieJar);
    assertHttp($res['code'] === 200, "Deploy PHP site returns HTTP 200");
    $json = json_decode($res['body'], true);
    assertHttp(!empty($json['success']), "Deploy PHP response indicates success");
    assertHttp($json['site']['type'] === 'php', "Site recognized as PHP type");

    echo "\n=== 10. Test Native PHP Execution, GET Query String & Sessions ===\n";
    $res = httpReq("http://$serverHost/test-php/index.php?msg=SalamKhabar");
    assertHttp($res['code'] === 200, "PHP index.php returns HTTP 200");
    assertHttp(str_contains($res['body'], 'SalamKhabar'), "GET Query String passed and rendered by PHP");
    assertHttp(str_contains($res['body'], 'نسخه PHP سرور'), "PHP dynamically executed server variables");

    echo "\n=== 11. Test PHP POST Form ===\n";
    $res = httpReq("http://$serverHost/test-php/index.php", 'POST', ['message' => 'PostSuccessMessage']);
    assertHttp($res['code'] === 200, "PHP POST request returns HTTP 200");
    assertHttp(str_contains($res['body'], 'PostSuccessMessage'), "PHP script processed POST parameter");

    echo "\n=== 12. Test PHP JSON API ===\n";
    $res = httpReq("http://$serverHost/test-php/api.php?key=123");
    assertHttp($res['code'] === 200, "API returns HTTP 200");
    $apiJson = json_decode($res['body'], true);
    assertHttp(($apiJson['status'] ?? '') === 'success', "API returned valid JSON status");
    assertHttp(($apiJson['query_params']['key'] ?? '') === '123', "API parsed query parameter in JSON");

    echo "\n=== 13. Test Deleting Site via Admin Panel ===\n";
    $postDelete = [
        'csrf_token' => $csrfToken,
        'slug' => 'test-static'
    ];
    $res = httpReq("http://$serverHost/$adminSlug/?action=delete", 'POST', $postDelete, ['X-Requested-With: XMLHttpRequest'], $cookieJar);
    assertHttp($res['code'] === 200, "Delete request returns HTTP 200");
    $json = json_decode($res['body'], true);
    assertHttp(!empty($json['success']), "Delete response indicates success");

    $res = httpReq("http://$serverHost/test-static/index.html");
    assertHttp($res['code'] === 404, "Deleted site index.html now returns 404 Not Found");

    echo "\n=== ALL HTTP INTEGRATION TESTS PASSED 100%! ===\n\n";

} finally {
    // Terminate server process cleanly
    echo "Shutting down test server...\n";
    foreach ($pipes as $p) {
        if (is_resource($p)) @fclose($p);
    }

    if (!empty($serverPid)) {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec("taskkill /F /T /PID $serverPid >NUL 2>&1");
        } else {
            @posix_kill($serverPid, SIGKILL);
        }
    }

    @proc_terminate($proc);
    @proc_close($proc);

    // Clean up sandbox
    \Panel\Security::deleteDirectory($testEnvDir);
}
