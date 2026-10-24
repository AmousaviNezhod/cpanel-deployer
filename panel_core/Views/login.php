<?php
declare(strict_types=1);

ob_start();
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="brand-icon">⚡</div>
            <h2>ورود به پنل دیپلوی</h2>
            <p>مدیریت و استقرار سایت‌های cPanel</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠</span>
                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($rate) && !$rate['allowed']): ?>
            <div class="alert alert-warning">
                <span>⏳</span>
                <div>
                    تعداد تلاش‌های ناموفق بیش از حد مجاز است. دسترسی تا
                    <strong><?= ceil($rate['retry_after'] / 60) ?> دقیقه</strong>
                    دیگر مسدود است.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="./?action=login">
            <div class="form-group">
                <label class="form-label" for="adminPassword">رمز عبور مدیریت</label>
                <input
                    type="password"
                    id="adminPassword"
                    name="password"
                    class="form-input"
                    placeholder="رمز عبور خود را وارد کنید..."
                    required
                    autofocus
                    autocomplete="current-password"
                    <?= (isset($rate) && !$rate['allowed']) ? 'disabled' : '' ?>
                >
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                style="width: 100%; padding: 0.75rem;"
                <?= (isset($rate) && !$rate['allowed']) ? 'disabled' : '' ?>
            >
                ورود به مدیریت
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.78rem; color: var(--text-muted);">
            سیستم مستقل میزبانی بدون دیتابیس
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'ورود مدیریت';
require __DIR__ . '/layout.php';
