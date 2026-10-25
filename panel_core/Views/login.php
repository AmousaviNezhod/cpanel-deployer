<?php
declare(strict_types=1);

/**
 * Modern Developer Login View
 *
 * @var string|null $error
 * @var array|null $rate
 */

ob_start();
?>
<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: var(--bg-canvas);">
    <div style="width: 100%; max-width: 400px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.25rem 2rem; box-shadow: var(--shadow-lg);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 1rem; width: 44px; height: 44px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <h1 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.35rem;">ورود به پنل دیپلوی (cPanel Deployer)</h1>
            <p style="font-size: 0.82rem; color: var(--text-muted);">سیستم مستقل استقرار و مدیریت هاست cPanel</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="padding: 0.75rem 1rem; background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: var(--radius-sm); color: #fca5a5; font-size: 0.82rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>⚠</span>
                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($rate) && !$rate['allowed']): ?>
            <div style="padding: 0.75rem 1rem; background: var(--warning-subtle); border: 1px solid var(--warning-border); border-radius: var(--radius-sm); color: #fde68a; font-size: 0.82rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>⏳</span>
                <div>
                    تعداد تلاش‌های ناموفق بیش از حد مجاز است. دسترسی تا
                    <strong><?= ceil($rate['retry_after'] / 60) ?> دقیقه</strong>
                    دیگر مسدود است.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="./?action=login">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="adminPassword">رمز عبور مدیریت</label>
                <input
                    type="password"
                    id="adminPassword"
                    name="password"
                    class="form-input"
                    placeholder="رمز عبور پنل را وارد کنید..."
                    required
                    autofocus
                    autocomplete="current-password"
                    style="direction: ltr;"
                    <?= (isset($rate) && !$rate['allowed']) ? 'disabled' : '' ?>
                >
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                style="width: 100%; padding: 0.7rem; font-size: 0.9rem;"
                <?= (isset($rate) && !$rate['allowed']) ? 'disabled' : '' ?>
            >
                ورود به پنل
            </button>
        </form>

        <div style="margin-top: 2rem; text-align: center; font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);">
            cPanel Deployer • Standalone Architecture
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'ورود به مدیریت';
require __DIR__ . '/layout.php';
