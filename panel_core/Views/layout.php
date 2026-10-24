<?php
declare(strict_types=1);
/**
 * Master Layout
 *
 * @var string $title
 * @var string $content
 * @var string|null $csrfToken
 */

$cssPath = dirname(__DIR__) . '/assets/app.css';
$jsPath = dirname(__DIR__) . '/assets/app.js';
$inlineCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';
$inlineJs = file_exists($jsPath) ? file_get_contents($jsPath) : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'مدیریت و دیپلوی سایت‌ها', ENT_QUOTES, 'UTF-8') ?> - cPanel Deployer</title>
    <?php if (!empty($csrfToken)): ?>
        <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        <?= $inlineCss ?>
    </style>
</head>
<body>
    <?= $content ?>

    <script>
        <?= $inlineJs ?>
    </script>
</body>
</html>
