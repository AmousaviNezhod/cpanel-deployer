<?php
declare(strict_types=1);

$serverData = [
    'PHP SAPI' => PHP_SAPI,
    'PHP Version' => PHP_VERSION,
    'Request URI' => $_SERVER['REQUEST_URI'] ?? '-',
    'Script Name' => $_SERVER['SCRIPT_NAME'] ?? '-',
    'Memory Usage' => round(memory_get_usage() / 1024, 2) . ' KB',
    'Loaded Extensions Count' => count(get_loaded_extensions()),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اطلاعات سرور PHP</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #090d16; color: #e2e8f0; padding: 2rem; direction: rtl; }
        .box { max-width: 600px; margin: 0 auto; background: #131b2e; border: 1px solid #1e293b; border-radius: 10px; padding: 1.5rem; }
        h1 { margin-top: 0; font-size: 1.4rem; color: #38bdf8; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        td, th { padding: 0.6rem; border-bottom: 1px solid #1e293b; text-align: right; }
        td:last-child { font-family: monospace; color: #a5b4fc; direction: ltr; }
        a { color: #38bdf8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <h1>مشخصات محیط اجرای PHP</h1>
        <p><a href="./index.php">&larr; بازگشت به صفحه اصلی</a></p>
        <table>
            <?php foreach ($serverData as $key => $val): ?>
                <tr>
                    <th><?= htmlspecialchars($key) ?></th>
                    <td><?= htmlspecialchars((string)$val) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
