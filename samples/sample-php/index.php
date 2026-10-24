<?php
declare(strict_types=1);

session_start();
$_SESSION['visit_count'] = ($_SESSION['visit_count'] ?? 0) + 1;

$postedMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = (string)($_POST['message'] ?? '');
    $postedMessage = trim(strip_tags($raw));
}

$queryMsg = (string)($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست اجرای PHP - cPanel Deployer</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #090d16;
            color: #e2e8f0;
            padding: 2rem 1rem;
            margin: 0;
            direction: rtl;
            line-height: 1.6;
        }
        .container { max-width: 650px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 2rem; }
        .badge {
            background: rgba(168, 85, 247, 0.2);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.4);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.6rem; color: #f8fafc; }
        .card {
            background: #131b2e;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .metric {
            background: #090d16;
            border: 1px solid #1e293b;
            padding: 0.85rem;
            border-radius: 8px;
        }
        .metric-title { font-size: 0.75rem; color: #94a3b8; }
        .metric-val { font-size: 1.1rem; font-weight: 700; color: #38bdf8; margin-top: 0.25rem; font-family: monospace; }
        input[type="text"] {
            width: 100%;
            background: #090d16;
            border: 1px solid #334155;
            padding: 0.65rem;
            color: #fff;
            border-radius: 6px;
            box-sizing: border-box;
            margin-bottom: 0.75rem;
            font-family: inherit;
        }
        button {
            background: #7c3aed;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }
        button:hover { background: #6d28d9; }
        nav { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; }
        nav a {
            color: #94a3b8;
            text-decoration: none;
            background: #131b2e;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            border: 1px solid #1e293b;
            font-size: 0.85rem;
        }
        nav a:hover { color: #fff; border-color: #38bdf8; }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="badge">PHP Native &bull; بدون دیتابیس</div>
            <h1>تست اجرای زنده PHP در هاست</h1>
            <p>این اسکریپت اجرای واقعی کدهای PHP، سشن‌ها، فرم‌های POST و پارامترهای GET را بررسی می‌کند.</p>
        </header>

        <nav>
            <a href="./index.php">خانه</a>
            <a href="./info.php">مشخصات سرور</a>
            <a href="./api.php">خروجی JSON API</a>
            <a href="./index.php?msg=سلام+تست+پارامتر">تست Query String</a>
        </nav>

        <?php if ($postedMessage !== ''): ?>
            <div class="alert-success">
                ✓ فرم POST با موفقیت پردازش شد! متن ارسالی: <strong><?= htmlspecialchars($postedMessage, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($queryMsg !== ''): ?>
            <div class="alert-success">
                ✓ پارامتر GET دریافت شد: <strong><?= htmlspecialchars($queryMsg, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>وضعیت اجرا در محیط cPanel</h3>
            <div class="grid">
                <div class="metric">
                    <div class="metric-title">نسخه PHP سرور</div>
                    <div class="metric-val"><?= PHP_VERSION ?></div>
                </div>
                <div class="metric">
                    <div class="metric-title">تعداد بازدید سشن فعلی</div>
                    <div class="metric-val"><?= $_SESSION['visit_count'] ?></div>
                </div>
                <div class="metric">
                    <div class="metric-title">زمان سرور (Timestamp)</div>
                    <div class="metric-val"><?= date('H:i:s') ?></div>
                </div>
                <div class="metric">
                    <div class="metric-title">متد درخواست جاری</div>
                    <div class="metric-val"><?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>تست ارسال فرم POST معمولی</h3>
            <form method="POST" action="./index.php">
                <input type="text" name="message" placeholder="یک پیام آزمایشی بنویسید..." required>
                <button type="submit">ارسال درخواست POST</button>
            </form>
        </div>
    </div>
</body>
</html>
