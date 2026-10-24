<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطای سیستم - cPanel Deployer</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Vazirmatn", sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
            direction: rtl;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
        }
        h1 {
            font-size: 1.3rem;
            margin-top: 0;
            color: #f87171;
        }
        p {
            color: #94a3b8;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        .btn {
            display: inline-block;
            margin-top: 1.2rem;
            padding: 0.6rem 1.4rem;
            background: #3b82f6;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <h1>خطای پیکربندی پنل</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="../install.php" class="btn">ورود به مرحله نصب</a>
    </div>
</body>
</html>
