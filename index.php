<?php
declare(strict_types=1);

/**
 * cPanel Deployer - Root Index
 * By default, this provides a clean landing page and does not reveal the secret admin panel path.
 * When a service is set as Primary / Root Website, it serves that project seamlessly at https://domain.com/.
 */

// If installer hasn't been run yet, direct user to install.php
if (!file_exists(__DIR__ . '/panel_data/install.lock')) {
    header('Location: install.php');
    exit;
}

$configFile = __DIR__ . '/panel_data/config.json';
$primarySlug = null;
if (file_exists($configFile)) {
    $cfg = json_decode((string)@file_get_contents($configFile), true);
    if (is_array($cfg) && !empty($cfg['primary_slug'])) {
        $primarySlug = (string)$cfg['primary_slug'];
    }
}

if ($primarySlug !== null) {
    $primaryDir = __DIR__ . '/' . $primarySlug;
    if (is_dir($primaryDir)) {
        if (file_exists($primaryDir . '/index.php')) {
            chdir($primaryDir);
            require $primaryDir . '/index.php';
            exit;
        }
        if (file_exists($primaryDir . '/index.html')) {
            $html = (string)file_get_contents($primaryDir . '/index.html');
            // Ensure base href points to root for root visitors
            $html = preg_replace(
                '#<base\s+href=([\'"])/' . preg_quote($primarySlug, '#') . '/?([^\'"]*)\1>#i',
                '<base href=$1/$2$1>',
                $html
            );
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        }
        if (file_exists($primaryDir . '/index.htm')) {
            $html = (string)file_get_contents($primaryDir . '/index.htm');
            $html = preg_replace(
                '#<base\s+href=([\'"])/' . preg_quote($primarySlug, '#') . '/?([^\'"]*)\1>#i',
                '<base href=$1/$2$1>',
                $html
            );
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>میزبانی وب</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #0b0f19;
            color: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            direction: rtl;
        }
        .box {
            text-align: center;
            padding: 2.5rem;
            background: #111827;
            border: 1px solid #374151;
            border-radius: 12px;
            max-width: 440px;
        }
        h1 { font-size: 1.4rem; margin-top: 0; color: #3b82f6; }
        p { color: #9ca3af; font-size: 0.95rem; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="box">
        <h1>سایت در حال اجرا است</h1>
        <p>سرور فعال است و سرویس‌ها به‌درستی پاسخ می‌دهند.</p>
    </div>
</body>
</html>
