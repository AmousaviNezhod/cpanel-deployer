<?php
declare(strict_types=1);

/**
 * cPanel Deployer - Initial Web Installer
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$appRoot = __DIR__;
$dataPath = $appRoot . DIRECTORY_SEPARATOR . 'panel_data';
$lockFile = $dataPath . DIRECTORY_SEPARATOR . 'install.lock';
$configFile = $dataPath . DIRECTORY_SEPARATOR . 'config.json';

// Security check: Lock down installer if already installed
if (file_exists($lockFile) || (file_exists($configFile) && !empty(json_decode((string)file_get_contents($configFile), true)['installed']))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>نصب قفل شده است</title>
        <style>
            body { font-family: system-ui, sans-serif; background: #0b0f19; color: #f9fafb; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; direction: rtl; }
            .box { background: #111827; border: 1px solid #374151; border-radius: 12px; padding: 2rem; max-width: 460px; text-align: center; }
            h2 { color: #f87171; margin-top: 0; }
            p { color: #9ca3af; font-size: 0.95rem; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>فرآیند نصب قبلاً تکمیل شده است</h2>
            <p>به دلایل امنیتی، امکان اجرای مجدد فایل نصب وجود ندارد. برای ورود، از آدرس اختصاصی پنل مدیریت خود استفاده کنید.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// System Environment Checks
$requirements = [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'اکستنشن Zip' => extension_loaded('zip'),
    'اکستنشن JSON' => extension_loaded('json'),
    'اکستنشن Fileinfo' => extension_loaded('fileinfo'),
    'قابلیت Session' => function_exists('session_start'),
    'دسترسی نوشتن در ریشه' => is_writable($appRoot),
];

$allReqsPassed = !in_array(false, $requirements, true);

// Auto-generate suggested random panel slug: e.g. panel-d4c2b8e1a90f
$suggestedSlug = 'panel-' . bin2hex(random_bytes(8));

$error = null;
$completed = false;
$finalPanelUrl = '';
$finalAdminSlug = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allReqsPassed) {
    $password = (string)($_POST['admin_password'] ?? '');
    $passwordConfirm = (string)($_POST['admin_password_confirm'] ?? '');
    $adminSlug = strtolower(trim((string)($_POST['admin_slug'] ?? '')));

    if (strlen($password) < 8) {
        $error = 'طول رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'رمز عبور و تکرار آن یکسان نیستند.';
    } elseif (!preg_match('/^[a-z0-9][a-z0-9_-]{3,40}$/', $adminSlug)) {
        $error = 'شناسه مسیر پنل فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد، - و _ باشد (حداقل ۴ و حداکثر ۴۰ کاراکتر).';
    } elseif (in_array($adminSlug, ['panel_core', 'panel_data', 'api', 'admin', 'index.php', 'install.php'], true)) {
        $error = 'این مسیر توسط سیستم رزرو شده است. لطفاً مسیر دیگری انتخاب کنید.';
    } else {
        // Create admin entry folder
        $adminDir = $appRoot . DIRECTORY_SEPARATOR . $adminSlug;
        if (is_dir($adminDir)) {
            $error = 'پوشه‌ای با این نام در حال حاضر در هاست وجود دارد.';
        } else {
            if (!@mkdir($adminDir, 0755, true)) {
                $error = 'امکان ایجاد پوشه پنل مدیریت وجود ندارد. لطفاً دسترسی نوشتن پوشه را بررسی کنید.';
            } else {
                // Write admin index.php entrypoint
                $entrypointCode = "<?php\n"
                    . "declare(strict_types=1);\n"
                    . "define('APP_ROOT', dirname(__DIR__));\n"
                    . "require_once APP_ROOT . '/panel_core/Bootstrap.php';\n"
                    . "\\Panel\\Bootstrap::run();\n";

                file_put_contents($adminDir . DIRECTORY_SEPARATOR . 'index.php', $entrypointCode, LOCK_EX);
                @chmod($adminDir . DIRECTORY_SEPARATOR . 'index.php', 0644);

                // Save config.json
                if (!is_dir($dataPath)) {
                    @mkdir($dataPath, 0755, true);
                }

                $configData = [
                    'installed' => true,
                    'admin_slug' => $adminSlug,
                    'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'secret_key' => bin2hex(random_bytes(32)),
                    'installed_at' => date('Y-m-d H:i:s'),
                    'max_upload_bytes' => 104857600,   // 100 MB
                    'max_extract_bytes' => 314572800,  // 300 MB
                    'max_file_count' => 5000
                ];

                file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                @chmod($configFile, 0644);

                // Ensure sites.json exists
                $sitesFile = $dataPath . DIRECTORY_SEPARATOR . 'sites.json';
                if (!file_exists($sitesFile)) {
                    file_put_contents($sitesFile, "{}", LOCK_EX);
                    @chmod($sitesFile, 0644);
                }

                // Write lock file
                file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s') . "\nAdmin slug: " . $adminSlug, LOCK_EX);
                @chmod($lockFile, 0644);

                // Build absolute URL
                $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
                $protocol = $isHttps ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $finalPanelUrl = $protocol . $host . '/' . $adminSlug . '/';
                $finalAdminSlug = $adminSlug;
                $completed = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب اولیه - cPanel Deployer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b0f19;
            --bg-card: #111827;
            --border: #374151;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius: 10px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Vazirmatn", Tahoma, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.6;
            direction: rtl;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .install-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: 100%;
            max-width: 520px;
            padding: 2.25rem 2rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
        }
        .header { text-align: center; margin-bottom: 1.75rem; }
        .logo-badge {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin: 0 auto 0.75rem;
        }
        h1 { font-size: 1.35rem; font-weight: 700; }
        p.subtitle { font-size: 0.88rem; color: var(--text-muted); margin-top: 0.25rem; }
        .req-list {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        .req-item { display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .req-item:last-child { border-bottom: none; }
        .req-status-ok { color: var(--success); font-weight: 600; }
        .req-status-fail { color: var(--danger); font-weight: 600; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
        input[type="text"], input[type="password"] {
            width: 100%;
            background: #1f2937;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
            color: var(--text-primary);
            font-family: inherit;
        }
        input:focus { outline: none; border-color: var(--accent); }
        .hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.75rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn:hover { background: var(--accent-hover); }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .alert-danger { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .success-box {
            text-align: center;
        }
        .url-display {
            background: #0b0f19;
            border: 1px solid var(--accent);
            border-radius: 8px;
            padding: 0.85rem;
            font-family: monospace;
            font-size: 0.95rem;
            color: #60a5fa;
            word-break: break-all;
            margin: 1.25rem 0;
            direction: ltr;
        }
        .copy-btn {
            background: #1f2937;
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        .copy-btn:hover { background: #374151; }
    </style>
</head>
<body>
    <div class="install-card">
        <div class="header">
            <div class="logo-badge">⚡</div>
            <h1>نصب cPanel Deployer</h1>
            <p class="subtitle">راه‌اندازی سبک و مستقل برای هاست اشتراکی</p>
        </div>

        <?php if ($completed): ?>
            <div class="success-box">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                <h2 style="font-size: 1.25rem; color: var(--success); margin-bottom: 0.5rem;">نصب با موفقیت انجام شد!</h2>
                <p style="font-size: 0.88rem; color: var(--text-secondary);">
                    مسیر اختصاصی و تصادفی پنل مدیریت شما با موفقیت ایجاد شد:
                </p>

                <div class="url-display" id="finalUrlText"><?= htmlspecialchars($finalPanelUrl, ENT_QUOTES, 'UTF-8') ?></div>

                <button type="button" class="copy-btn" id="copyUrlBtn">📋 کپی آدرس اختصاصی پنل</button>

                <div class="alert" style="background: rgba(245,158,11,0.15); color: #fde68a; border: 1px solid rgba(245,158,11,0.3); text-align: right;">
                    <strong>توجه مهم:</strong> حتماً این آدرس را ذخیره یا بوک‌مارک کنید. به دلایل امنیتی مسیر عمومی مثل <code>/admin</code> وجود ندارد. فایل <code>install.php</code> نیز به‌طور خودکار قفل شد.
                </div>

                <a href="<?= htmlspecialchars($finalPanelUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">ورود به پنل مدیریت</a>
            </div>

            <script>
                document.getElementById('copyUrlBtn').addEventListener('click', function() {
                    var url = document.getElementById('finalUrlText').innerText;
                    navigator.clipboard.writeText(url).then(function() {
                        document.getElementById('copyUrlBtn').innerText = '✓ کپی شد!';
                        setTimeout(function() {
                            document.getElementById('copyUrlBtn').innerText = '📋 کپی آدرس اختصاصی پنل';
                        }, 2000);
                    });
                });
            </script>
        <?php else: ?>
            <div class="req-list">
                <div style="font-weight: 600; margin-bottom: 0.4rem; color: var(--text-primary);">بررسی پیش‌نیازهای سرور:</div>
                <?php foreach ($requirements as $name => $passed): ?>
                    <div class="req-item">
                        <span><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="<?= $passed ? 'req-status-ok' : 'req-status-fail' ?>">
                            <?= $passed ? '✓ تأیید' : '✕ ناموفق' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!$allReqsPassed): ?>
                <div class="alert alert-danger">
                    پیش‌نیازهای هاست برآورده نشده است. لطفاً نسخه PHP و دسترسی نوشتن پوشه را در cPanel بررسی کنید.
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="adminSlug">مسیر تصادفی پنل (Admin Slug)</label>
                        <input
                            type="text"
                            id="adminSlug"
                            name="admin_slug"
                            value="<?= htmlspecialchars($suggestedSlug, ENT_QUOTES, 'UTF-8') ?>"
                            required
                            style="direction: ltr; text-align: left; font-family: monospace;"
                        >
                        <div class="hint">این شناسه یک مسیر غیرقابل حدس برای ورود به پنل ایجاد می‌کند.</div>
                    </div>

                    <div class="form-group">
                        <label for="adminPassword">رمز عبور مدیر</label>
                        <input
                            type="password"
                            id="adminPassword"
                            name="admin_password"
                            required
                            minlength="8"
                            placeholder="حداقل ۸ کاراکتر..."
                        >
                    </div>

                    <div class="form-group">
                        <label for="adminPasswordConfirm">تکرار رمز عبور مدیر</label>
                        <input
                            type="password"
                            id="adminPasswordConfirm"
                            name="admin_password_confirm"
                            required
                            minlength="8"
                            placeholder="تکرار رمز عبور..."
                        >
                    </div>

                    <button type="submit" class="btn">تکمیل نصب و راه‌اندازی پنل</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
