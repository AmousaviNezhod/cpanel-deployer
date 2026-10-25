<?php
declare(strict_types=1);

/**
 * Modern Developer Tool Dashboard View
 *
 * @var array $sites
 * @var array $diskStats
 * @var array $databases
 * @var string $csrfToken
 * @var string $adminSlug
 * @var string $maxUpload
 * @var string|null $primarySlug
 * @var array $recentLogs
 */

$isHttps = \Panel\Auth::isHttps();
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $host;

$hostStats = $diskStats['host'] ?? [];
$servicesStats = $diskStats['services'] ?? [];
$deployerStats = $diskStats['deployer'] ?? [];
$totalManaged = $diskStats['total_managed'] ?? [];
$sqliteDbs = $databases['sqlite'] ?? [];
$mysqlDbs = $databases['mysql'] ?? [];

// Determine latest deployment for the hero summary
$latestSite = null;
if (!empty($sites)) {
    $sortedSites = $sites;
    uasort($sortedSites, function ($a, $b) {
        $tA = strtotime($a['last_deploy_at'] ?? $a['created_at'] ?? '2000-01-01');
        $tB = strtotime($b['last_deploy_at'] ?? $b['created_at'] ?? '2000-01-01');
        return $tB <=> $tA;
    });
    $latestSite = reset($sortedSites);
}

ob_start();
?>
<!-- Header & Environment Bar -->
<header class="header">
    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>
        </div>
        <div>
            <div class="brand-title">
                cPanel Deployer
                <span class="brand-badge">v1.4</span>
            </div>
        </div>
    </div>

    <div class="header-meta">
        <div class="env-tag" title="دامنه اصلی هاست">
            <span class="env-dot"></span>
            <span><?= htmlspecialchars($host, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="env-tag" title="مسیر محرمانه مدیریت">
            <span style="color: var(--text-muted);">پنل:</span>
            <span>/<?= htmlspecialchars($adminSlug, ENT_QUOTES, 'UTF-8') ?>/</span>
        </div>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-primary" id="openNewDeployBtn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>استقرار پروژه جدید</span>
        </button>
        <form method="POST" action="./?action=logout" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-secondary btn-sm" title="خروج از حساب">
                خروج
            </button>
        </form>
    </div>
</header>

<main class="container">
    <!-- Hero Status Deck -->
    <div class="overview-deck">
        <!-- Card 1: Latest Deployment & Environment -->
        <div class="overview-card">
            <div class="overview-header">
                <span class="overview-label">سایت‌های مستقر و وضعیت سیستم</span>
                <span style="font-size: 0.76rem; color: var(--text-muted); font-family: var(--font-mono);">
                    <?= count($sites) ?> پروژه فعال
                </span>
            </div>

            <?php if ($latestSite): ?>
                <div class="overview-highlight">
                    <div class="overview-val-big"><?= htmlspecialchars($latestSite['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="overview-val-sub" style="direction: ltr;">/<?= htmlspecialchars($latestSite['slug'], ENT_QUOTES, 'UTF-8') ?>/</div>
                </div>
                <div class="overview-details">
                    <span class="status-pill status-pill-success">
                        <span style="width: 5px; height: 5px; border-radius: 50%; background: #34d399;"></span>
                        مستقر در سرور
                    </span>
                    <?php if (!empty($latestSite['has_php']) || ($latestSite['type'] ?? '') === 'php'): ?>
                        <span class="status-pill status-pill-info">PHP ⚡</span>
                    <?php else: ?>
                        <span class="status-pill status-pill-muted">Static</span>
                    <?php endif; ?>
                    <?php if ($primarySlug === $latestSite['slug']): ?>
                        <span class="status-pill status-pill-warning">صفحه اصلی دامنه (/)</span>
                    <?php endif; ?>
                    <span style="color: var(--text-muted); font-family: var(--font-mono); direction: ltr;">
                        <?= htmlspecialchars($latestSite['last_deploy_at'] ?? $latestSite['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="overview-highlight">
                    <div class="overview-val-big" style="font-size: 1.25rem; color: var(--text-muted);">پروژه‌ای مستقر نشده است</div>
                </div>
                <div class="overview-details" style="color: var(--text-muted);">
                    برای شروع، با استفاده از دکمه «استقرار پروژه جدید» اولین سایت خود را دیپلوی کنید.
                </div>
            <?php endif; ?>
        </div>

        <!-- Card 2: Host Quota & Storage Footprint -->
        <div class="overview-card">
            <div class="overview-header">
                <span class="overview-label">سهمیه هاست و مصرف فضا</span>
                <button type="button" class="btn btn-ghost btn-sm" id="openQuotaEditBtn" style="padding: 0.15rem 0.4rem; font-size: 0.74rem;">
                    ⚙ تنظیم سهمیه
                </button>
            </div>
            <div class="overview-highlight">
                <div class="overview-val-big"><?= htmlspecialchars($hostStats['used_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="overview-val-sub">از سقف <?= htmlspecialchars($hostStats['total_formatted'] ?? '200 MB', ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <!-- Multi-segment Storage Bar -->
            <div class="storage-meter">
                <div class="storage-meter-track">
                    <?php
                    $quotaUsedPct = min(100, max(2, (float)($hostStats['used_percent'] ?? 0)));
                    $sBytes = (int)($servicesStats['total_bytes'] ?? 0);
                    $dBytes = (int)($deployerStats['total_bytes'] ?? 0);
                    $totalB = max(1, $sBytes + $dBytes);
                    $sPct = ($sBytes / $totalB) * $quotaUsedPct;
                    $dPct = ($dBytes / $totalB) * $quotaUsedPct;
                    ?>
                    <div class="storage-meter-fill" style="width: <?= max(1, $sPct) ?>%; background: #6366f1;" title="سایت‌ها: <?= htmlspecialchars($servicesStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="storage-meter-fill" style="width: <?= max(1, $dPct) ?>%; background: #f59e0b;" title="هسته دپلویر: <?= htmlspecialchars($deployerStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
                <div class="storage-meter-legend">
                    <span>مصرف کل: <?= $hostStats['used_percent'] ?? 0 ?>%</span>
                    <span>سایت‌ها: <?= htmlspecialchars($servicesStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?></span>
                    <span>دپلویر: <?= htmlspecialchars($deployerStats['total_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?></span>
                    <span style="color: var(--success);"><?= htmlspecialchars($hostStats['free_formatted'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?> آزاد</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <button class="tab-btn active" data-tab="tab-sites">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span>پروژه‌ها و استقرارها</span>
            <span class="tab-counter"><?= count($sites) ?></span>
        </button>
        <button class="tab-btn" data-tab="tab-files">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>مدیریت فایل و کد پروژه‌ها</span>
        </button>
        <button class="tab-btn" data-tab="tab-databases">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
            </svg>
            <span>مدیریت پایگاه‌های داده</span>
            <span class="tab-counter"><?= count($sqliteDbs) + count($mysqlDbs) ?></span>
        </button>
        <button class="tab-btn" data-tab="tab-logs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 17 10 11 4 5"></polyline>
                <line x1="12" y1="19" x2="20" y2="19"></line>
            </svg>
            <span>لاگ‌ها و رویدادهای سرور</span>
            <span class="tab-counter"><?= count($recentLogs) ?></span>
        </button>
    </div>

    <!-- TAB 1: SITES & DEPLOYMENTS -->
    <div id="tab-sites" class="tab-pane active">
        <!-- Toolbar: Search & Filters -->
        <div class="toolbar">
            <div class="toolbar-search-wrapper">
                <input type="text" id="siteSearchInput" class="toolbar-search-input" placeholder="جستجوی نام یا مسیر پروژه...">
                <span class="toolbar-search-icon">🔍</span>
            </div>

            <div class="toolbar-actions">
                <div class="mode-toggle-group" style="margin-bottom: 0;">
                    <button type="button" class="mode-toggle-btn active" data-filter="all">همه (<?= count($sites) ?>)</button>
                    <button type="button" class="mode-toggle-btn" data-filter="php">PHP</button>
                    <button type="button" class="mode-toggle-btn" data-filter="static">استاتیک</button>
                    <button type="button" class="mode-toggle-btn" data-filter="github">گیت‌هاب</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="toolbarDeployBtn">
                    + استقرار جدید
                </button>
            </div>
        </div>

        <!-- Sites Table or Empty State -->
        <div class="table-card">
            <?php if (empty($sites)): ?>
                <!-- Meaningful Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">هنوز پروژه‌ای مستقر نشده است</div>
                    <div class="empty-state-desc">
                        شما می‌توانید فایل‌های پروژه خود را به صورت پکیج ZIP آپلود کرده یا مستقیماً آدرس ریپازیتوری گیت‌هاب را متصل کنید تا با تنظیمات خودکار روت‌ها روی cPanel اجرا شود.
                    </div>
                    <button type="button" class="btn btn-primary" id="emptyStateDeployBtn">
                        + استقرار اولین پروژه
                    </button>
                </div>
            <?php else: ?>
                <table class="data-table" id="sitesDataTable">
                    <thead>
                        <tr>
                            <th style="width: 28%;">پروژه و شناسه مسیر</th>
                            <th style="width: 20%;">آدرس مستقیم</th>
                            <th style="width: 12%;">محیط و وضعیت</th>
                            <th style="width: 12%;">حجم اشغال‌شده</th>
                            <th style="width: 12%;">آخرین دیپلوی</th>
                            <th style="width: 16%; text-align: left;">عملیات</th>
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
                            $isGh = !empty($site['github']['url']);
                            $isRoot = ($primarySlug === $site['slug']);
                            $filterType = $isGh ? 'github' : ($isPhp ? 'php' : 'static');
                            ?>
                            <tr data-site-row data-slug="<?= $slug ?>" data-name="<?= strtolower($name) ?>" data-type="<?= $filterType ?>">
                                <td>
                                    <div class="project-identity">
                                        <div class="project-title">
                                            <span class="project-name"><?= $name ?></span>
                                            <?php if ($isRoot): ?>
                                                <span class="status-pill status-pill-warning" title="اختصاص یافته به عنوان صفحه اصلی دامنه">
                                                    🏠 ریشه دامنه (/)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="project-meta-row">
                                            <span class="project-slug">/<?= $slug ?>/</span>
                                            <?php if ($isGh): ?>
                                                <a href="<?= htmlspecialchars($site['github']['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="gh-repo-link" title="مشاهده مخزن گیت‌هاب">
                                                    <span>🐙</span>
                                                    <span style="direction: ltr; font-family: var(--font-mono);"><?= htmlspecialchars($site['github']['owner'] . '/' . $site['github']['repo'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="status-pill status-pill-muted" style="padding: 0 4px; font-size: 0.65rem;"><?= htmlspecialchars($site['github']['branch'] ?? 'main', ENT_QUOTES, 'UTF-8') ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="site-url-box">
                                        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="site-direct-link" title="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            /<?= $slug ?>/
                                        </a>
                                        <button type="button" class="btn btn-ghost btn-xs btn-copy-icon" data-copy="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" title="کپی آدرس کامل">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.3rem; align-items: flex-start;">
                                        <?php if ($isPhp): ?>
                                            <span class="status-pill status-pill-info">PHP ⚡</span>
                                        <?php else: ?>
                                            <span class="status-pill status-pill-muted">استاتیک</span>
                                        <?php endif; ?>

                                        <?php if (!empty($site['exists'])): ?>
                                            <span class="status-pill status-pill-success" style="font-size: 0.68rem;">
                                                فعال (<?= htmlspecialchars($site['index_file'] ?? 'index', ENT_QUOTES, 'UTF-8') ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="status-pill status-pill-warning" style="font-size: 0.68rem;">فاقد فایل Index</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-primary); direction: ltr; text-align: right;">
                                        <?= htmlspecialchars($site['formatted_size'] ?? '0 B', ENT_QUOTES, 'UTF-8') ?>
                                        <span style="font-size: 0.7rem; color: var(--text-muted);">(<?= $pct ?>%)</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-secondary); direction: ltr; text-align: right;">
                                        <?= htmlspecialchars($site['last_deploy_at'] ?? $site['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="مشاهده سایت در تب جدید">
                                            مشاهده ↗
                                        </a>

                                        <a href="?action=download_site&slug=<?= $slug ?>" class="btn btn-download-zip btn-sm" title="دانلود آخرین سورس مستقر شده پروژه به صورت فایل ZIP">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            <span>دانلود ZIP</span>
                                        </a>

                                        <button class="btn btn-secondary btn-sm" data-action="open-files" data-slug="<?= $slug ?>" title="مدیریت و ویرایش فایل‌های این پروژه">
                                            📁 فایل‌ها
                                        </button>

                                        <div class="action-dropdown-wrap">
                                            <button type="button" class="btn btn-ghost btn-sm btn-more-toggle" title="عملیات بیشتر">
                                                •••
                                            </button>
                                            <div class="action-dropdown-menu">
                                                <a href="?action=download_site&slug=<?= $slug ?>" class="action-dropdown-item">
                                                    <span class="dropdown-item-icon">📦</span>
                                                    <span>دانلود آخرین پکیج دیپلوی (ZIP)</span>
                                                </a>
                                                <button type="button" class="action-dropdown-item" data-action="open-update" data-slug="<?= $slug ?>" data-name="<?= $name ?>" data-fix-routes="<?= !empty($site['fix_routes']) ? '1' : '0' ?>">
                                                    <span class="dropdown-item-icon">🔄</span>
                                                    <span>به‌روزرسانی با فایل ZIP جدید</span>
                                                </button>
                                                <?php if ($isGh): ?>
                                                    <button type="button" class="action-dropdown-item" data-action="sync-github" data-slug="<?= $slug ?>" data-name="<?= $name ?>">
                                                        <span class="dropdown-item-icon">🐙</span>
                                                        <span>همگام‌سازی آخرین کامیت گیت‌هاب</span>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isRoot): ?>
                                                    <button type="button" class="action-dropdown-item" data-action="unset-primary" data-slug="<?= $slug ?>">
                                                        <span class="dropdown-item-icon">❌</span>
                                                        <span>لغو اختصاص به ریشه دامنه</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="action-dropdown-item" data-action="set-primary" data-slug="<?= $slug ?>" data-name="<?= $name ?>">
                                                        <span class="dropdown-item-icon">🏠</span>
                                                        <span>تنظیم به عنوان صفحه اصلی دامنه (Root /)</span>
                                                    </button>
                                                <?php endif; ?>
                                                <div class="action-dropdown-divider"></div>
                                                <button type="button" class="action-dropdown-item text-danger" data-action="open-delete" data-slug="<?= $slug ?>" data-name="<?= $name ?>">
                                                    <span class="dropdown-item-icon">🗑️</span>
                                                    <span>حذف کامل پروژه از هاست</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: FILE MANAGER & CODE STUDIO -->
    <div id="tab-files" class="tab-pane">
        <div class="fm-container">
            <!-- File Manager Header -->
            <div class="fm-toolbar">
                <div class="fm-project-selector-wrapper">
                    <label style="font-size: 0.84rem; color: var(--text-muted); font-weight: 500;">پروژه انتخابی:</label>
                    <select id="fmProjectSelect" class="form-input" style="width: auto; min-width: 180px; padding: 0.4rem 0.75rem;">
                        <option value="">-- انتخاب پروژه --</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?= htmlspecialchars($site['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?> (/<?= htmlspecialchars($site['slug'], ENT_QUOTES, 'UTF-8') ?>/)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fm-breadcrumbs-bar" id="fmBreadcrumbs">
                    <span class="fm-crumb-item active">/</span>
                </div>

                <div class="fm-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="fmDownloadZipBtn" title="دانلود کل پروژه انتخابی به صورت فایل ZIP">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>دانلود ZIP پروژه</span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="fmRefreshBtn" title="تازه‌سازی لیست فایل‌ها">🔄 رفرش</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="fmNewFileBtn">📄 فایل جدید</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="fmNewDirBtn">📁 پوشه جدید</button>
                    <button type="button" class="btn btn-primary btn-sm" id="fmOpenUploadModalBtn">⬆ آپلود در این پوشه</button>
                </div>
            </div>

            <!-- Explorer & Code Editor View -->
            <div class="fm-main-card">
                <!-- Directory Table View -->
                <div id="fmExplorerView">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">نام فایل / پوشه</th>
                                <th style="width: 15%;">حجم</th>
                                <th style="width: 20%;">تاریخ آخرین ویرایش</th>
                                <th style="width: 15%; text-align: center;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="fmTableBody">
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                    لطفاً از منوی بالا یک پروژه را برای مشاهده و ویرایش فایل‌ها انتخاب نمایید.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- In-Browser Code Editor -->
                <div id="fmEditorView" style="display: none;">
                    <div class="fm-editor-header">
                        <div class="fm-editor-file-info">
                            <strong id="fmEditorFilename" style="color: var(--text-primary); font-family: var(--font-mono); font-size: 0.95rem;">-</strong>
                            <span id="fmEditorSyntaxBadge" class="status-pill status-pill-info" style="font-size: 0.68rem; font-family: var(--font-mono);">CODE</span>
                            <span id="fmEditorSize" style="font-size: 0.78rem; color: var(--text-muted); font-family: var(--font-mono);">-</span>
                            <span id="fmEditorDirty" style="color: var(--warning); font-size: 0.78rem; display: none;">• تغییرات ذخیره نشده</span>
                        </div>
                        <div class="fm-editor-actions">
                            <button type="button" id="fmEditorCloseBtn" class="btn btn-secondary btn-sm">بستن ادیتور</button>
                            <button type="button" id="fmEditorSaveBtn" class="btn btn-primary btn-sm">💾 ذخیره فایل (Ctrl+S)</button>
                        </div>
                    </div>

                    <div class="fm-editor-wrapper">
                        <div class="fm-editor-line-numbers" id="fmEditorLineNumbers">1</div>
                        <textarea id="fmEditorTextarea" class="fm-editor-textarea" spellcheck="false" placeholder="محتوای فایل در حال بارگذاری است..."></textarea>
                    </div>

                    <div class="fm-editor-status">
                        <span id="fmEditorStatusText">آماده</span>
                        <span id="fmEditorCursorPos">Ln 1, Col 1</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: DATABASE STUDIO -->
    <div id="tab-databases" class="tab-pane">
        <div class="db-studio-grid">
            <!-- Sidebar: Available Databases -->
            <div class="db-sidebar">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">پایگاه‌های داده</span>
                    <div style="display: flex; gap: 0.35rem;">
                        <button type="button" class="btn btn-secondary btn-sm" id="openNewSqliteModal" title="ساخت دیتابیس SQLite">+ SQLite</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="openNewMysqlModal" title="اتصال به دیتابیس MySQL">+ MySQL</button>
                    </div>
                </div>

                <!-- SQLite List -->
                <div class="db-list-group">
                    <span style="font-size: 0.74rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">SQLite</span>
                    <?php if (empty($sqliteDbs)): ?>
                        <div style="font-size: 0.78rem; color: var(--text-muted); padding: 0.4rem 0;">دیتابیس SQLite ساخته نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($sqliteDbs as $db): ?>
                            <div class="db-item-btn" data-db-type="sqlite" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" data-db-name="<?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); font-family: var(--font-mono); direction: ltr;"><?= htmlspecialchars($db['filename'], ENT_QUOTES, 'UTF-8') ?> (<?= $db['formatted_size'] ?>)</div>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" data-action="delete-db" data-db-type="sqlite" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--danger); padding: 0.2rem 0.4rem;" title="حذف">✕</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- MySQL List -->
                <div class="db-list-group">
                    <span style="font-size: 0.74rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">MySQL / MariaDB</span>
                    <?php if (empty($mysqlDbs)): ?>
                        <div style="font-size: 0.78rem; color: var(--text-muted); padding: 0.4rem 0;">ارتباط MySQL ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($mysqlDbs as $db): ?>
                            <div class="db-item-btn" data-db-type="mysql" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" data-db-name="<?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($db['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); font-family: var(--font-mono); direction: ltr;"><?= htmlspecialchars($db['database'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" data-action="delete-db" data-db-type="mysql" data-db-id="<?= htmlspecialchars($db['id'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--danger); padding: 0.2rem 0.4rem;" title="حذف">✕</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Panel: SQL Console & Tables -->
            <div class="db-main-panel">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary);" id="activeDbTitle">
                            دیتابیس انتخاب نشده است
                        </span>
                        <div id="activeDbBadgeContainer"></div>
                    </div>
                </div>

                <!-- Tables Strip -->
                <div>
                    <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 0.35rem;">جداول پایگاه داده (کلیک برای کوئری سریع):</div>
                    <div class="db-tables-strip" id="dbTablesStrip">
                        <span style="font-size: 0.8rem; color: var(--text-muted);">ابتدا دیتابیس را انتخاب نمایید.</span>
                    </div>
                </div>

                <!-- SQL Editor -->
                <div class="sql-editor-container">
                    <div class="sql-editor-actions">
                        <div style="display: flex; gap: 0.3rem;">
                            <button type="button" class="btn btn-ghost btn-sm sql-quick-btn" data-template="SELECT * FROM {table} LIMIT 25;">SELECT</button>
                            <button type="button" class="btn btn-ghost btn-sm sql-quick-btn" data-template="INSERT INTO {table} VALUES ();">INSERT</button>
                            <button type="button" class="btn btn-ghost btn-sm sql-quick-btn" data-template="CREATE TABLE sample (id INTEGER PRIMARY KEY, title TEXT);">CREATE TABLE</button>
                        </div>
                        <button id="runSqlQueryBtn" class="btn btn-primary btn-sm" disabled>
                            ▶ اجرای کوئری (Ctrl+Enter)
                        </button>
                    </div>
                    <textarea id="sqlQueryTextarea" class="sql-textarea" placeholder="کوئری SQL خود را اینجا بنویسید... (مانند: SELECT * FROM users;)"></textarea>
                </div>

                <!-- Query Results -->
                <div id="sqlResultsContainer">
                    <div style="padding: 1.5rem; color: var(--text-muted); font-size: 0.82rem; text-align: center;">
                        نتیجه اجرای دستور SQL در اینجا نمایش داده خواهد شد.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: AUDIT & DEPLOYMENT LOGS -->
    <div id="tab-logs" class="tab-pane">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <div>
                <strong style="color: var(--text-primary); font-size: 0.95rem;">گزارش رویدادها و استقرارهای سرور</strong>
                <div style="font-size: 0.78rem; color: var(--text-muted);">ثبت تمامی عملیات‌های استقرار، تغییرات فایل، و دسترسی‌ها</div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="refreshLogsBtn">
                🔄 به‌روزرسانی لاگ‌ها
            </button>
        </div>

        <div class="table-card" style="padding: 0.5rem;">
            <div class="logs-timeline" id="logsTimeline">
                <?php if (empty($recentLogs)): ?>
                    <div style="padding: 2.5rem; text-align: center; color: var(--text-muted); font-size: 0.84rem;">
                        هنوز رویدادی ثبت نشده است.
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item-row">
                            <span class="log-item-time"><?= htmlspecialchars($log['time']) ?></span>
                            <span class="log-item-ip"><?= htmlspecialchars($log['ip']) ?></span>
                            <span class="log-item-msg"><?= htmlspecialchars($log['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<footer class="footer">
    cPanel Deployer • سیستم سبک و مستقل استقرار وب و مانیتورینگ هاست اشتراکی • PHP <?= PHP_VERSION ?>
</footer>

<!-- ==========================================================================
     MODALS
     ========================================================================== -->

<!-- Modal: Guided New Deployment Wizard -->
<div id="deployModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title">استقرار پروژه جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <!-- Wizard Stepper Header -->
        <div class="wizard-stepper" id="deployStepper">
            <div class="wizard-step active" data-step="1">
                <div class="wizard-step-num">1</div>
                <div class="wizard-step-title">منبع پروژه</div>
            </div>
            <div class="wizard-step-sep">/</div>
            <div class="wizard-step" data-step="2">
                <div class="wizard-step-num">2</div>
                <div class="wizard-step-title">مشخصات و مسیر</div>
            </div>
            <div class="wizard-step-sep">/</div>
            <div class="wizard-step" data-step="3">
                <div class="wizard-step-num">3</div>
                <div class="wizard-step-title">تنظیمات و تایید</div>
            </div>
        </div>

        <form id="deployForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="deployModeInput" name="deploy_mode" value="zip">

            <div class="modal-body" id="deployFormBody">
                <!-- STEP 1: SOURCE SELECTION -->
                <div class="wizard-pane active" data-pane="1">
                    <label class="form-label">نحوه دریافت فایل‌های پروژه را انتخاب کنید:</label>
                    <div class="mode-toggle-group">
                        <button type="button" class="mode-toggle-btn active deploy-source-tab-btn" data-deploy-source="zip">
                            <span>📦</span>
                            <span>آپلود پکیج ZIP</span>
                        </button>
                        <button type="button" class="mode-toggle-btn deploy-source-tab-btn" data-deploy-source="github">
                            <span>🐙</span>
                            <span>کلون از مخزن GitHub</span>
                        </button>
                    </div>

                    <!-- Source: ZIP Section -->
                    <div id="wizardZipSource">
                        <div id="deployDropzone" class="dropzone">
                            <input type="file" id="deployZipInput" name="zip_file" accept=".zip,application/zip" required>
                            <div class="dropzone-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                            </div>
                            <div class="dropzone-title">فایل ZIP پروژه را اینجا رها کنید یا کلیک کنید</div>
                            <div class="dropzone-sub">پوشه‌های والد اضافی به‌طور هوشمند حذف می‌شوند (حداکثر سقف مجاز: <?= $maxUpload ?>)</div>
                        </div>
                        <div id="deployFilename" class="selected-filename-box" style="display: none;"></div>
                    </div>

                    <!-- Source: GitHub Section -->
                    <div id="wizardGithubSource" style="display: none;">
                        <div class="form-group">
                            <label class="form-label" for="deployGhUrlInput"><span class="req">*</span> آدرس مخزن گیت‌هاب (Repository URL)</label>
                            <input type="url" id="deployGhUrlInput" name="github_url" class="form-input" placeholder="https://github.com/username/repository" style="direction: ltr; text-align: left;">
                            <div class="form-hint">ریپازیتوری‌های پابلیک بدون احراز هویت دانلود می‌شوند.</div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="deployGhBranchInput">شاخه (Branch)</label>
                                <input type="text" id="deployGhBranchInput" name="github_branch" class="form-input" placeholder="main یا master (خودکار)" style="direction: ltr; text-align: left;">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="deployGhTokenInput">توکن شخصی (PAT)</label>
                                <input type="password" id="deployGhTokenInput" name="github_token" class="form-input" placeholder="ghp_... (فقط برای مخازن خصوصی)" style="direction: ltr; text-align: left;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: PROJECT NAME & TARGET SLUG -->
                <div class="wizard-pane" data-pane="2">
                    <div class="form-group">
                        <label class="form-label" for="siteNameInput"><span class="req">*</span> نام نمایشی پروژه</label>
                        <input type="text" id="siteNameInput" name="name" class="form-input" placeholder="مثال: فروشگاه آنلاین کفش" required>
                        <div class="form-hint">یک عنوان دلخواه و خوانا جهت نمایش در داشبورد و فهرست سرویس‌ها.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="siteSlugInput"><span class="req">*</span> شناسه مسیر در هاست (Slug)</label>
                        <input type="text" id="siteSlugInput" name="slug" class="form-input" placeholder="مثال: sportshoes" required pattern="[a-z0-9][a-z0-9_-]*" style="direction: ltr; text-align: left; font-family: var(--font-mono);">
                        <div class="form-hint">
                            پیش‌نمایش آدرس نهایی: <code id="slugUrlPreview" style="color: var(--accent); font-family: var(--font-mono);"><?= rtrim($baseUrl, '/') ?>/slug/</code>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: CONFIGURATION & REVIEW -->
                <div class="wizard-pane" data-pane="3">
                    <div class="form-group" style="background: var(--bg-card); padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <label style="display: flex; align-items: flex-start; gap: 0.65rem; cursor: pointer; margin-bottom: 0;">
                            <input type="checkbox" id="deployFixRoutesCheckbox" name="fix_routes" value="1" checked style="margin-top: 0.25rem;">
                            <div>
                                <strong style="color: var(--text-primary); font-size: 0.86rem;">اصلاح خودکار روت‌ها و مسیرهای زیرشاخه (Subpath Auto-Fix)</strong>
                                <div class="form-hint" style="margin-top: 0.2rem;">
                                    تزریق خودکار تگ <code>&lt;base href&gt;</code> در فایل‌های HTML و تصحیح مسیرهای ریشه (مانند <code>/assets/...</code>) برای اجرای بدون خطای استایل‌ها و تصاویر.
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Summary Card -->
                    <div style="background: rgba(0, 0, 0, 0.25); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.85rem; margin-top: 1rem;">
                        <span style="font-size: 0.74rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">خلاصه مشخصات استقرار:</span>
                        <div style="display: grid; grid-template-columns: 100px 1fr; gap: 0.4rem; font-size: 0.82rem;">
                            <span style="color: var(--text-muted);">پروژه:</span>
                            <span id="summaryProjectName" style="color: var(--text-primary); font-weight: 600;">-</span>

                            <span style="color: var(--text-muted);">منبع:</span>
                            <span id="summarySource" style="color: var(--text-primary); font-family: var(--font-mono); direction: ltr; text-align: right;">-</span>

                            <span style="color: var(--text-muted);">مسیر نهایی:</span>
                            <span id="summaryUrl" style="color: var(--accent); font-family: var(--font-mono); direction: ltr; text-align: right;">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Execution & Progress View (Active during deployment) -->
            <div id="deployProgressView" class="modal-body deploy-execution-view">
                <div class="deploy-stages-list">
                    <div class="deploy-stage-item" id="stageUpload">
                        <span class="stage-dot">1</span>
                        <span>ارسال و بارگذاری پکیج پروژه در هاست</span>
                    </div>
                    <div class="deploy-stage-item" id="stageVerify">
                        <span class="stage-dot">2</span>
                        <span>بازرسی امنیتی پکیج و محافظت در برابر Zip Slip</span>
                    </div>
                    <div class="deploy-stage-item" id="stageExtract">
                        <span class="stage-dot">3</span>
                        <span>استخراج ایمن فایل‌ها و حذف پوشه‌های ریشه اضافه</span>
                    </div>
                    <div class="deploy-stage-item" id="stageRoute">
                        <span class="stage-dot">4</span>
                        <span>پیکربندی وب‌سرور و تطبیق روت‌های زیرمسیر</span>
                    </div>
                    <div class="deploy-stage-item" id="stageComplete">
                        <span class="stage-dot">5</span>
                        <span>تأیید نهایی سلامت و استقرار سرویس</span>
                    </div>
                </div>

                <!-- Expandable Terminal Logs -->
                <div class="terminal-card">
                    <div class="terminal-header">
                        <span>خروجی زنده استقرار (Deployment Log)</span>
                        <span id="terminalStatusIndicator">در حال پردازش...</span>
                    </div>
                    <div class="terminal-body" id="deployTerminalOutput">> شروع فرایند استقرار...</div>
                </div>

                <!-- Success Actions Strip -->
                <div id="deploySuccessActions" style="display: none; margin-top: 1.25rem; text-align: center;">
                    <div style="font-size: 0.95rem; font-weight: 600; color: var(--success); margin-bottom: 0.85rem;">
                        ✓ پروژه با موفقیت مستقر و در هاست فعال شد!
                    </div>
                    <div style="display: flex; gap: 0.65rem; justify-content: center;">
                        <a href="" id="deploySuccessOpenLink" target="_blank" rel="noopener" class="btn btn-primary">
                            مشاهده سایت زنده ↗
                        </a>
                        <button type="button" id="deploySuccessFilesBtn" class="btn btn-secondary">
                            مدیریت فایل‌ها 📁
                        </button>
                        <button type="button" class="btn btn-secondary" data-close-modal>
                            بستن
                        </button>
                    </div>
                </div>

                <!-- Error Actions Strip -->
                <div id="deployErrorActions" style="display: none; margin-top: 1.25rem;">
                    <div style="padding: 0.75rem 1rem; background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: var(--radius-sm); color: #fca5a5; font-size: 0.84rem; margin-bottom: 1rem;" id="deployErrorMessageText">
                        خطایی رخ داده است.
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button type="button" class="btn btn-secondary" id="deployRetryBtn">
                            اصلاح و تلاش مجدد
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Navigation Footer -->
            <div class="modal-footer" id="deployModalFooter">
                <button type="button" class="btn btn-secondary" id="wizardPrevBtn" style="display: none;">
                    ← مرحله قبل
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" class="btn btn-secondary" data-close-modal id="wizardCancelBtn">
                    انصراف
                </button>
                <button type="button" class="btn btn-primary" id="wizardNextBtn">
                    مرحله بعد: مشخصات پروژه →
                </button>
                <button type="submit" class="btn btn-primary" id="wizardSubmitBtn" style="display: none;">
                    🚀 شروع دیپلوی
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Site ZIP -->
<div id="updateModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">به‌روزرسانی پروژه</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="updateForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="updateSlugInput" name="slug" value="">

            <div class="modal-body">
                <p style="font-size: 0.86rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    پروژه در حال به‌روزرسانی: <strong id="updateSiteNameDisplay" style="color: var(--text-primary); font-family: var(--font-mono);">-</strong>
                </p>

                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> فایل ZIP جدید</label>
                    <div id="updateDropzone" class="dropzone">
                        <input type="file" id="updateZipInput" name="zip_file" accept=".zip,application/zip" required>
                        <div class="dropzone-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        <div class="dropzone-title">فایل ZIP جدید را اینجا رها کنید</div>
                        <div class="dropzone-sub">به‌روزرسانی اتمیک با امکان Rollback خودکار انجام می‌شود</div>
                    </div>
                    <div id="updateFilename" class="selected-filename-box" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="updateFixRoutesCheckbox" name="fix_routes" value="1" checked>
                        <span style="font-size: 0.84rem; color: var(--text-primary);">اصلاح خودکار روت‌ها و مسیرهای زیرشاخه (Subpath Auto-Fix)</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">شروع به‌روزرسانی</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Site -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--danger);">حذف کامل پروژه</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" id="deleteSlugInput" name="slug" value="">

            <div class="modal-body">
                <p style="font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.85rem;">
                    آیا از حذف کامل پروژه <strong id="deleteTargetName">-</strong> اطمینان دارید؟
                </p>
                <div style="padding: 0.75rem 1rem; background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: var(--radius-sm); font-size: 0.8rem; color: #fca5a5;">
                    تمامی فایل‌های پوشه <code id="deleteTargetPath" style="font-family: var(--font-mono); direction: ltr; font-weight: 600;">-</code> برای همیشه از هاست حذف خواهند شد. این عملیات غیرقابل بازگشت است.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-danger">بله، حذف کن</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create SQLite Database -->
<div id="sqliteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">ایجاد پایگاه داده SQLite جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="sqliteForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sqliteNameInput"><span class="req">*</span> نام نمایشی دیتابیس</label>
                    <input type="text" id="sqliteNameInput" name="name" class="form-input" placeholder="مثال: دیتابیس بلاگ" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sqliteFilenameInput"><span class="req">*</span> نام فایل (بدون پسوند .sqlite)</label>
                    <input type="text" id="sqliteFilenameInput" name="filename" class="form-input" placeholder="مثال: blog_data" required style="direction: ltr;">
                    <div class="form-hint">فایل دیتابیس در مسیر امن <code>panel_data/databases/</code> ایجاد خواهد شد.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ایجاد دیتابیس</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Save MySQL Connection -->
<div id="mysqlModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">اتصال به پایگاه داده MySQL / MariaDB</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="mysqlForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="mysqlTitleInput">نام نمایشی اتصال</label>
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
            <div class="modal-title">تنظیم سقف سهمیه اکانت هاست (Account Quota)</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="quotaForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="quotaMbInput"><span class="req">*</span> سقف سهمیه اکانت (مگابایت)</label>
                    <input type="number" id="quotaMbInput" name="quota_mb" class="form-input" value="<?= $customQuotaMb ?? 200 ?>" min="10" max="1000000" required style="direction: ltr;">
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

<!-- File Manager Modals -->
<div id="fmNewFileModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">ایجاد فایل جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="fmNewFileForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fmNewFileNameInput"><span class="req">*</span> نام فایل با پسوند</label>
                    <input type="text" id="fmNewFileNameInput" name="filename" class="form-input" placeholder="مثال: custom.css یا header.php" required style="direction: ltr;">
                    <div class="form-hint">فایل در مسیر پوشه فعلی پروژه ایجاد خواهد شد.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ایجاد فایل</button>
            </div>
        </form>
    </div>
</div>

<div id="fmNewDirModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">ایجاد پوشه جدید</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="fmNewDirForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fmNewDirNameInput"><span class="req">*</span> نام پوشه</label>
                    <input type="text" id="fmNewDirNameInput" name="dirname" class="form-input" placeholder="مثال: components یا uploads" required style="direction: ltr;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ایجاد پوشه</button>
            </div>
        </form>
    </div>
</div>

<div id="fmRenameModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">تغییر نام</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="fmRenameForm">
            <input type="hidden" id="fmRenameOldPathInput" name="old_path" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fmRenameNewNameInput"><span class="req">*</span> نام جدید</label>
                    <input type="text" id="fmRenameNewNameInput" name="new_name" class="form-input" required style="direction: ltr;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">ذخیره نام جدید</button>
            </div>
        </form>
    </div>
</div>

<div id="fmDeleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--danger);">حذف فایل یا پوشه</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="fmDeleteForm">
            <input type="hidden" id="fmDeletePathInput" name="item_path" value="">
            <div class="modal-body">
                <p style="font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.85rem;">آیا از حذف مورد زیر اطمینان دارید؟</p>
                <div style="background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: var(--radius-sm); padding: 0.75rem 1rem; color: #fca5a5; font-family: var(--font-mono); direction: ltr; word-break: break-all;" id="fmDeleteTargetDisplay">
                    -
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-danger">بله، حذف شود</button>
            </div>
        </form>
    </div>
</div>

<div id="fmUploadModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">آپلود فایل در پوشه فعلی</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="fmUploadForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> انتخاب فایل</label>
                    <div id="fmUploadDropzone" class="dropzone">
                        <input type="file" id="fmUploadFileInput" name="file" required>
                        <div class="dropzone-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="dropzone-title">فایل را اینجا رها کنید یا کلیک کنید</div>
                        <div class="dropzone-sub">تصاویر، استایل‌ها، اسکریپت‌ها یا کدهای برنامه مجاز هستند</div>
                    </div>
                    <div id="fmUploadFilename" class="selected-filename-box" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>انصراف</button>
                <button type="submit" class="btn btn-primary">آپلود</button>
            </div>
        </form>
    </div>
</div>

<div id="fmImageModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <div class="modal-title" id="fmImageTitle">پیش‌نمایش تصویر</div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 1.5rem; background: rgba(0,0,0,0.3); border-radius: var(--radius-sm);">
            <img id="fmImagePreview" src="" alt="preview" style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        </div>
        <div class="modal-footer">
            <a id="fmImageDownloadBtn" href="" target="_blank" download class="btn btn-secondary">باز کردن در تب جدید ↗</a>
            <button type="button" class="btn btn-primary" data-close-modal>بستن</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'داشبورد مدیریت و استقرار';
require __DIR__ . '/layout.php';
