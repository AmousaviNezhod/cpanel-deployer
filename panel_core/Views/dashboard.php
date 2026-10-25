<?php
declare(strict_types=1);

/**
 * Dashboard View
 *
 * @var array $sites
 * @var array $diskStats
 * @var array $databases
 * @var string $csrfToken
 * @var string $adminSlug
 * @var string $maxUpload
 */

$isHttps = \Panel\Auth::isHttps();
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $host;

$hostStats = $diskStats['host'] ?? [];
$servicesStats = $diskStats['services'] ?? [];
$sqliteDbs = $databases['sqlite'] ?? [];
$mysqlDbs = $databases['mysql'] ?? [];

$palette = ['#3b82f6', '#10b981', '#f59e0b', '#a855f7', '#06b6d4', '#ec4899', '#84cc16'];

ob_start();
?>
<header class="header">
    <div class="brand">
        <div class="brand-icon">⚡</div>
        <div class="brand-text">
            <h1>cPanel Deployer</h1>
            <span>سیستم استقرار، مانیتورینگ فضا و مدیریت دیتابیس</span>
        </div>
    </div>
    <div class="header-actions">
        <span class="url-chip" title="مسیر اختصاصی این پنل">
            <?= htmlspecialchars($adminSlug, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <form method="POST" action="./?action=logout" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-secondary btn-sm" title="خروج از حساب">
                خروج
            </button>
        </form>
    </div>
</header>

<main class="container">
    <!-- Bento Overview Grid -->
    <div class="bento-grid">
        <!-- 1. Host Disk Space -->
        <div class="bento-card">
            <div class="bento-header">
                <span class="bento-title">سهمیه اکانت هاست (cPanel Quota)</span>
                <span class="bento-icon">💽</span>
            </div>
            <div class="bento-value">
                <?= htmlspecialchars($hostStats['used_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="bento-sub">
                <span>از سقف سهمیه <?= htmlspecialchars($hostStats['total_formatted'] ?? '200 MB', ENT_QUOTES, 'UTF-8') ?></span>
                <span>•</span>
                <span style="color: var(--success);"><?= htmlspecialchars($hostStats['free_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?> آزاد</span>
                <span>(<?= $hostStats['used_percent'] ?? 0 ?>%)</span>
            </div>
            <div class="bento-progress-bg">
                <div class="bento-progress-fill" style="width: <?= min(100, max(2, (float)($hostStats['used_percent'] ?? 0))) ?>%; background: <?= ((float)($hostStats['used_percent'] ?? 0) > 85) ? 'var(--danger)' : 'var(--accent)' ?>;"></div>
            </div>
            <?php if (!empty($hostStats['partition_total_formatted']) && $hostStats['partition_total_formatted'] !== $hostStats['total_formatted']): ?>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>پارتیشن کل سرور فیزیکی: <?= htmlspecialchars($hostStats['partition_total_formatted'], ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" class="copy-btn" id="openQuotaEditBtn" title="تنظیم دستی سقف سهمیه">⚙ تنظیم سهمیه</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Services Storage Footprint -->
        <div class="bento-card">
            <div class="bento-header">
                <span class="bento-title">فضای مصرفی سرویس‌ها</span>
                <span class="bento-icon">📦</span>
            </div>
            <div class="bento-value" style="color: #60a5fa;">
                <?= htmlspecialchars($servicesStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="bento-sub">
                <span>شامل <?= (int)($servicesStats['total_files'] ?? 0) ?> فایل</span>
                <span>•</span>
                <span><?= count($sites) ?> سرویس مستقر</span>
            </div>
            <div class="bento-progress-bg">
                <div class="bento-progress-fill" style="width: <?= count($sites) > 0 ? '100' : '0' ?>%; background: #60a5fa;"></div>
            </div>
        </div>

        <!-- 3. Deployed Sites Status -->
        <div class="bento-card">
            <div class="bento-header">
                <span class="bento-title">وضعیت سرویس‌ها</span>
                <span class="bento-icon">🚀</span>
            </div>
            <div class="bento-value" style="color: var(--success);">
                <?= count($sites) ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;">سایت</span>
            </div>
            <div class="bento-sub">
                <?php
                $phpCount = 0;
                $staticCount = 0;
                foreach ($sites as $s) {
                    if (!empty($s['has_php']) || ($s['type'] ?? '') === 'php') {
                        $phpCount++;
                    } else {
                        $staticCount++;
                    }
                }
                ?>
                <span class="badge badge-php" style="padding: 1px 6px; font-size: 0.72rem;"><?= $phpCount ?> PHP</span>
                <span class="badge badge-static" style="padding: 1px 6px; font-size: 0.72rem;"><?= $staticCount ?> استاتیک</span>
            </div>
            <div class="bento-progress-bg">
                <div class="bento-progress-fill" style="width: 100%; background: var(--success);"></div>
            </div>
        </div>

        <!-- 4. Databases Studio Overview -->
        <div class="bento-card" style="cursor: pointer;" onclick="document.querySelector('[data-tab=tab-databases]').click()">
            <div class="bento-header">
                <span class="bento-title">پایگاه‌های داده</span>
                <span class="bento-icon">🗄️</span>
            </div>
            <div class="bento-value" style="color: var(--cyan);">
                <?= count($sqliteDbs) + count($mysqlDbs) ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;">دیتابیس</span>
            </div>
            <div class="bento-sub">
                <span class="badge badge-sqlite" style="padding: 1px 6px; font-size: 0.72rem;"><?= count($sqliteDbs) ?> SQLite</span>
                <span class="badge badge-mysql" style="padding: 1px 6px; font-size: 0.72rem;"><?= count($mysqlDbs) ?> MySQL</span>
            </div>
            <div class="bento-progress-bg">
                <div class="bento-progress-fill" style="width: 100%; background: var(--cyan);"></div>
            </div>
        </div>
    </div>

    <!-- Storage Distribution Visualizer -->
    <?php if (!empty($sites)): ?>
        <div class="storage-overview-card">
            <div class="storage-overview-header">
                <span class="storage-overview-title">تفکیک مصرف فضای هر سرویس روی هاست</span>
                <span style="font-size: 0.8rem; color: var(--text-muted);">
                    مجموع سرویس‌ها: <?= htmlspecialchars($servicesStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <div class="storage-bar-wrapper">
                <?php $colorIdx = 0; ?>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $color = $palette[$colorIdx % count($palette)];
                    $colorIdx++;
                    $pct = max(1.5, (float)($site['size_percent'] ?? 0));
                    ?>
                    <div class="storage-bar-segment"
                         style="width: <?= $pct ?>%; background-color: <?= $color ?>;"
                         title="<?= htmlspecialchars($site['name'] ?? $site['slug'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($site['formatted_size'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?> (<?= $site['size_percent'] ?? 0 ?>%)">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="storage-legend">
                <?php $colorIdx = 0; ?>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $color = $palette[$colorIdx % count($palette)];
                    $colorIdx++;
                    ?>
                    <div class="legend-item" title="/<?= htmlspecialchars($site['slug'], ENT_QUOTES, 'UTF-8') ?>/">
                        <span class="legend-dot" style="background-color: <?= $color ?>;"></span>
                        <strong><?= htmlspecialchars($site['name'] ?? $site['slug'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                        <span><?= htmlspecialchars($site['formatted_size'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?> (<?= $site['size_percent'] ?? 0 ?>%)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <button class="tab-btn active" data-tab="tab-services">
            <span>🌐</span> سایت‌ها و سرویس‌ها (<?= count($sites) ?>)
        </button>
        <button class="tab-btn" data-tab="tab-databases">
            <span>🗄️</span> مدیریت دیتابیس (<?= count($sqliteDbs) + count($mysqlDbs) ?>)
        </button>
    </div>

    <!-- TAB 1: SITES & DEPLOYMENTS -->
    <div id="tab-services" class="tab-pane active">
        <!-- Security Notice -->
        <div class="notice-box">
            <strong>نکات امنیتی و ساختار مسیردهی در هاست اشتراکی:</strong>
            <ul class="notice-list">
                <li>
                    <strong>ایزولاسیون:</strong> اسکریپت‌های PHP آپلودشده با دسترسی حساب cPanel شما اجرا می‌شوند؛ فقط کدهای معتبر و مطمئن را آپلود کنید.
                </li>
                <li>
                    <strong>مسیردهی دارایی‌ها:</strong> برای لود صحیح استایل‌ها و تصاویر در زیرمسیرها (مانند <code>/slug/</code>)، از آدرس‌های نسبی (<code>./assets/</code>) یا تگ <code>&lt;base href="/slug/"&gt;</code> استفاده نمایید.
                </li>
            </ul>
        </div>

        <!-- Action Toolbar -->
        <div class="toolbar">
            <div>
                <span class="toolbar-title">فهرست سایت‌های مستقر</span>
                <span class="toolbar-count">(<?= count($sites) ?> سرویس)</span>
            </div>
            <div class="toolbar-actions">
                <button id="openDeployModal" class="btn btn-primary">
                    <span>+</span> دیپلوی سایت جدید
                </button>
            </div>
        </div>

        <!-- Sites Table or Empty State -->
        <div class="table-card">
            <?php if (empty($sites)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <div class="empty-title">هنوز هیچ سایتی دیپلوی نشده است</div>
                    <div class="empty-desc">
                        می‌توانید یک فایل ZIP شامل یک سایت استاتیک (HTML/CSS/JS) یا یک پروژه PHP را آپلود و مستقر کنید.
                    </div>
                    <button onclick="document.getElementById('openDeployModal').click()" class="btn btn-primary">
                        دیپلوی اولین سایت
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>نام و شناسه سایت</th>
                                <th>آدرس دسترسی</th>
                                <th>نوع سرویس</th>
                                <th>حجم و سهم فضا</th>
                                <th>وضعیت فایل‌ها</th>
                                <th>آخرین دیپلوی</th>
                                <th style="text-align: center;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                                <?php
                                $slug = htmlspecialchars($site['slug'], ENT_QUOTES, 'UTF-8');
                                $name = htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8');
                                $siteUrl = rtrim($baseUrl, '/') . '/' . $site['slug'] . '/';
                                $isPhp = !empty($site['has_php']) || ($site['type'] ?? '') === 'php';
                                $pct = (float)($site['size_percent'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?= $name ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); direction: ltr; text-align: right;">
                                            /<?= $slug ?>/
                                        </div>
                                    </td>
                                    <td>
                                        <div class="url-chip">
                                            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                /<?= $slug ?>/
                                            </a>
                                            <button class="copy-btn" data-copy="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" title="کپی آدرس کامل">
                                                کپی
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isPhp): ?>
                                            <span class="badge badge-php">PHP ⚡</span>
                                        <?php else: ?>
                                            <span class="badge badge-static">استاتیک</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="service-size-cell">
                                            <div class="service-size-text">
                                                <?= htmlspecialchars($site['formatted_size'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>
                                                <span style="font-size: 0.72rem; color: var(--text-muted);">(<?= $pct ?>%)</span>
                                            </div>
                                            <div class="service-size-bar-bg" title="<?= $pct ?>% از کل سرویس‌ها">
                                                <div class="service-size-bar-fill" style="width: <?= min(100, max(4, $pct)) ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($site['exists'])): ?>
                                            <?php if (!empty($site['index_file'])): ?>
                                                <span class="badge badge-success">فعال (<?= htmlspecialchars($site['index_file'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning" title="فایل index یافت نشد">فاقد Index</span>
                                            <?php endif; ?>
                                            <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 0.2rem;">
                                                <?= (int)($site['file_count'] ?? 0) ?> فایل
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-danger">پوشه ناموجود</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary); direction: ltr; display: inline-block;">
                                            <?= htmlspecialchars($site['last_deploy_at'] ?? $site['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                            <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="مشاهده سایت در تب جدید">
                                                مشاهده
                                            </a>
                                            <button class="btn btn-secondary btn-sm" data-action="open-update" data-slug="<?= $slug ?>" data-name="<?= $name ?>" title="به‌روزرسانی با ZIP جدید">
                                                به‌روزرسانی
                                            </button>
                                            <button class="btn btn-danger btn-sm" data-action="open-delete" data-slug="<?= $slug ?>" data-name="<?= $name ?>" title="حذف کامل سایت">
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: DATABASE MANAGER -->
    <div id="tab-databases" class="tab-pane">
        <div class="db-studio-grid">
            <!-- Sidebar: DB List -->
            <div class="db-sidebar">
                <div class="db-list-header">
                    <strong style="font-size: 0.95rem;">پایگاه‌های داده</strong>
                    <div style="display: flex; gap: 0.35rem;">
                        <button id="openNewSqliteModal" class="btn btn-secondary btn-sm" title="ساخت دیتابیس SQLite جدید">+ SQLite</button>
                        <button id="openNewMysqlModal" class="btn btn-secondary btn-sm" title="اتصال دیتابیس MySQL">+ MySQL</button>
                    </div>
                </div>

                <div class="db-list" id="dbItemsList">
                    <!-- SQLite Section -->
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; margin-top: 0.25rem;">
                        دیتابیس‌های SQLite
                    </div>
                    <?php if (empty($sqliteDbs)): ?>
                        <div style="font-size: 0.8rem; color: var(--text-muted); padding: 0.5rem 0;">هیچ دیتابیس SQLite یافت نشد.</div>
                    <?php else: ?>
                        <?php foreach ($sqliteDbs as $db): ?>
                            <div class="db-item-card" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" data-db-type="sqlite" data-db-name="<?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="db-item-title">
                                    <span><?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge badge-sqlite">SQLite</span>
                                </div>
                                <div class="db-item-meta">
                                    <span>حجم: <?= htmlspecialchars($db['formatted_size'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <button class="copy-btn" data-delete-db="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--danger);" title="حذف دیتابیس">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- MySQL Section -->
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; margin-top: 0.75rem;">
                        اتصال‌های MySQL
                    </div>
                    <?php if (empty($mysqlDbs)): ?>
                        <div style="font-size: 0.8rem; color: var(--text-muted); padding: 0.5rem 0;">اتصال MySQL ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($mysqlDbs as $db): ?>
                            <div class="db-item-card" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" data-db-type="mysql" data-db-name="<?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="db-item-title">
                                    <span><?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge badge-mysql">MySQL</span>
                                </div>
                                <div class="db-item-meta">
                                    <span><?= htmlspecialchars($db['database'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($db['host'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <button class="copy-btn" data-delete-mysql="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--danger);" title="حذف اتصال">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main: Query Console & Tables -->
            <div class="db-main">
                <div class="db-active-header">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">دیتابیس انتخاب‌شده:</div>
                        <h3 id="activeDbTitle" style="font-size: 1.1rem; color: var(--text-primary); margin-top: 0.2rem;">
                            یک دیتابیس را از ستون کناری انتخاب کنید
                        </h3>
                    </div>
                    <div id="activeDbBadgeContainer"></div>
                </div>

                <!-- Tables Strip -->
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.4rem;">جداول پایگاه داده (برای کوئری سریع کلیک کنید):</div>
                    <div class="db-tables-strip" id="dbTablesStrip">
                        <span style="font-size: 0.82rem; color: var(--text-muted);">ابتدا دیتابیس را انتخاب نمایید.</span>
                    </div>
                </div>

                <!-- SQL Query Studio -->
                <div class="sql-editor-container">
                    <div class="sql-editor-actions">
                        <div class="sql-quick-buttons">
                            <button type="button" class="sql-quick-btn" data-template="SELECT * FROM {table} LIMIT 25;">SELECT</button>
                            <button type="button" class="sql-quick-btn" data-template="INSERT INTO {table} VALUES ();">INSERT</button>
                            <button type="button" class="sql-quick-btn" data-template="CREATE TABLE sample (id INTEGER PRIMARY KEY, title TEXT);">CREATE TABLE</button>
                        </div>
                        <button id="runSqlQueryBtn" class="btn btn-primary btn-sm" disabled>
                            <span>▶</span> اجرای کوئری (Ctrl+Enter)
                        </button>
                    </div>
                    <textarea id="sqlQueryTextarea" class="sql-textarea" placeholder="کوئری SQL خود را اینجا بنویسید... (مانند: SELECT * FROM users;)"></textarea>
                </div>

                <!-- Results Output -->
                <div id="sqlResultsContainer">
                    <div style="padding: 1rem; color: var(--text-muted); font-size: 0.85rem; text-align: center;">
                        نتیجه اجرای دستور SQL در اینجا نمایش داده خواهد شد.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="footer">
    cPanel Deployer - استقرار سبک، مانیتورینگ مصرف حافظه و مدیریت پایگاه‌های داده
</footer>

<!-- Modal: New Site Deployment -->
<div id="deployModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">دیپلوی سایت جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="deployForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="form-group">
                    <label class="form-label" for="siteNameInput">نام دلخواه سایت</label>
                    <input type="text" id="siteNameInput" name="name" class="form-input" placeholder="مثال: فروشگاه کفش">
                </div>

                <div class="form-group">
                    <label class="form-label" for="siteSlugInput"><span class="req">*</span> شناسه مسیر (Slug)</label>
                    <input type="text" id="siteSlugInput" name="slug" class="form-input" placeholder="مثال: sportshoes" required pattern="[a-z0-9][a-z0-9_-]*" style="direction: ltr; text-align: left;">
                    <div class="form-hint">
                        پیش‌نمایش آدرس نهایی: <code id="slugUrlPreview" style="color: var(--accent);"><?= rtrim($baseUrl, '/') ?>/slug/</code>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> فایل ZIP سایت</label>
                    <div id="deployDropzone" class="dropzone">
                        <input type="file" id="deployZipInput" name="zip_file" accept=".zip,application/zip" required>
                        <div class="dropzone-icon">📦</div>
                        <div class="dropzone-text">فایل ZIP سایت را اینجا رها کنید یا کلیک کنید</div>
                        <div class="dropzone-sub">پوشه‌های اصلی اضافی داخل ZIP به‌طور خودکار حذف می‌شوند (حداکثر <?= $maxUpload ?>)</div>
                    </div>
                    <div id="deployFilename" class="selected-filename"></div>
                </div>

                <div class="progress-container">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span class="progress-status">در حال ارسال فایل...</span>
                        <span class="progress-percent">0%</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">شروع دیپلوی</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Existing Site -->
<div id="updateModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">به‌روزرسانی سایت</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="updateForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" id="updateSlugInput" name="slug" value="">

                <div style="background: rgba(255,255,255,0.03); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 1.25rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted);">سایت در حال به‌روزرسانی:</div>
                    <strong id="updateSiteNameDisplay" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
                </div>

                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> فایل ZIP نسخه جدید</label>
                    <div id="updateDropzone" class="dropzone">
                        <input type="file" id="updateZipInput" name="zip_file" accept=".zip,application/zip" required>
                        <div class="dropzone-icon">🔄</div>
                        <div class="dropzone-text">فایل ZIP جدید را اینجا رها کنید یا کلیک کنید</div>
                        <div class="dropzone-sub">فایل‌ها ابتدا در پوشه موقت بررسی شده و سپس جایگزین می‌شوند.</div>
                    </div>
                    <div id="updateFilename" class="selected-filename"></div>
                </div>

                <div class="form-hint" style="color: var(--warning);">
                    نکته: در صورت بروز هرگونه خطا در استخراج، نسخه فعلی سایت بدون تغییر حفظ خواهد شد.
                </div>

                <div class="progress-container">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span class="progress-status">در حال آپلود و جایگزینی...</span>
                        <span class="progress-percent">0%</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">جایگزینی و به‌روزرسانی</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--danger);">تأیید حذف سایت</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="deleteForm">
            <div class="modal-body">
                <input type="hidden" id="deleteSlugInput" name="slug" value="">
                <p style="color: var(--text-primary); font-size: 0.95rem; line-height: 1.7;">
                    آیا از حذف کامل سایت <strong id="deleteTargetName" style="color: #fff;"></strong> در مسیر <code id="deleteTargetPath" style="color: var(--accent);"></code> اطمینان دارید؟
                </p>
                <div class="alert alert-danger" style="margin-top: 1rem; margin-bottom: 0;">
                    <span>⚠</span>
                    <div>این عملیات تمامی فایل‌ها و محتویات پوشه این سایت را حذف می‌کند و غیرقابل بازگشت است.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-danger">بله، حذف کن</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create SQLite DB -->
<div id="sqliteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">ایجاد دیتابیس SQLite جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="sqliteForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sqliteNameInput"><span class="req">*</span> نام دیتابیس</label>
                    <input type="text" id="sqliteNameInput" name="name" class="form-input" placeholder="مثال: app_database" required pattern="[a-zA-Z0-9_-]+" style="direction: ltr; text-align: left;">
                    <div class="form-hint">فایل به صورت خودکار با پسوند <code>.sqlite</code> در پوشه حفاظت‌شده <code>panel_data/databases/</code> ذخیره می‌شود.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ایجاد دیتابیس</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Connect MySQL DB -->
<div id="mysqlModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">اتصال پایگاه داده MySQL / MariaDB</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="mysqlForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="mysqlTitleInput">عنوان نمایشی</label>
                    <input type="text" id="mysqlTitleInput" name="name" class="form-input" placeholder="مثال: دیتابیس اصلی فروشگاه">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mysqlHostInput"><span class="req">*</span> هاست (Host)</label>
                    <input type="text" id="mysqlHostInput" name="host" class="form-input" value="localhost" required style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mysqlDbInput"><span class="req">*</span> نام پایگاه داده (Database Name)</label>
                    <input type="text" id="mysqlDbInput" name="database" class="form-input" placeholder="مثال: amousavi_shop" required style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mysqlUserInput"><span class="req">*</span> نام کاربری (Username)</label>
                    <input type="text" id="mysqlUserInput" name="username" class="form-input" placeholder="مثال: amousavi_user" required style="direction: ltr;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="mysqlPassInput"><span class="req">*</span> رمز عبور (Password)</label>
                    <input type="password" id="mysqlPassInput" name="password" class="form-input" required style="direction: ltr;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">تست و ذخیره اتصال</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Set Custom Quota -->
<div id="quotaModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">تنظیم سقف سهمیه هاست (Account Quota)</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="quotaForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="quotaMbInput"><span class="req">*</span> سقف سهمیه اکانت (مگابایت)</label>
                    <input type="number" id="quotaMbInput" name="quota_mb" class="form-input" value="200" min="10" max="1000000" required style="direction: ltr;">
                    <div class="form-hint">حجم کل سهمیه خریداری‌شده هاست cPanel خود را به مگابایت وارد نمایید (مثال: 200).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ذخیره سهمیه</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'داشبورد مدیریت';
require __DIR__ . '/layout.php';
