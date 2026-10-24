<?php
declare(strict_types=1);

/**
 * cPanel Deployer - Root Index
 * By default, this provides a clean landing page and does not reveal the secret admin panel path.
 */

// If installer hasn't been run yet, direct user to install.php
if (!file_exists(__DIR__ . '/panel_data/install.lock')) {
    header('Location: install.php');
    exit;
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
