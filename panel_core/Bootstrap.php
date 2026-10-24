<?php
declare(strict_types=1);

namespace Panel;

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/SiteManager.php';
require_once __DIR__ . '/Deployer.php';

final class Bootstrap
{
    private static ?array $config = null;
    private static string $appRoot = '';
    private static string $dataPath = '';
    private static string $publicHtmlPath = '';

    /**
     * Main entry point for the panel.
     */
    public static function run(): void
    {
        self::initEnvironment();
        self::sendSecurityHeaders();

        if (!self::isInstalled()) {
            http_response_code(503);
            self::renderError('سیستم هنوز نصب نشده است. لطفاً فایل install.php را در مرورگر اجرا کنید.');
            return;
        }

        Auth::startSession();

        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePostRequest($action);
            return;
        }

        self::handleGetRequest($action);
    }

    /**
     * Initialize environment paths and load config.
     */
    public static function initEnvironment(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__));
        }

        self::$appRoot = APP_ROOT;
        self::$publicHtmlPath = APP_ROOT;

        // Detect data path: check if parent directory has panel_data, or local
        $parentData = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . 'panel_data';
        $localData = APP_ROOT . DIRECTORY_SEPARATOR . 'panel_data';

        if (is_dir($parentData) && is_writable($parentData)) {
            self::$dataPath = $parentData;
        } else {
            self::$dataPath = $localData;
        }

        if (!is_dir(self::$dataPath)) {
            @mkdir(self::$dataPath, 0755, true);
        }

        $configFile = self::$dataPath . DIRECTORY_SEPARATOR . 'config.json';
        if (file_exists($configFile)) {
            $content = @file_get_contents($configFile);
            if ($content !== false && $content !== '') {
                self::$config = json_decode($content, true) ?: [];
            }
        }
    }

    /**
     * Send standard web security headers.
     */
    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Check if installation is complete.
     */
    public static function isInstalled(): bool
    {
        return !empty(self::$config['installed']) && !empty(self::$config['admin_password_hash']);
    }

    /**
     * Get active config.
     */
    public static function getConfig(): array
    {
        return self::$config ?? [];
    }

    /**
     * Get data path.
     */
    public static function getDataPath(): string
    {
        return self::$dataPath;
    }

    /**
     * Get public_html path.
     */
    public static function getPublicHtmlPath(): string
    {
        return self::$publicHtmlPath;
    }

    /**
     * Handle POST actions.
     */
    private static function handlePostRequest(string $action): void
    {
        // 1. Login action (unauthenticated)
        if ($action === 'login') {
            self::actionLogin();
            return;
        }

        // All subsequent POST actions require authentication & CSRF validation
        if (!Auth::isAuthenticated()) {
            self::jsonResponse(['success' => false, 'error' => 'احراز هویت نامعتبر یا منقضی شده است. لطفاً مجدداً وارد شوید.'], 401);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Auth::validateCsrfToken($csrfToken)) {
            self::jsonResponse(['success' => false, 'error' => 'توکن امنیتی (CSRF) نامعتبر است. صفحه را رفرش کنید.'], 403);
            return;
        }

        match ($action) {
            'logout' => self::actionLogout(),
            'deploy' => self::actionDeploy(false),
            'update' => self::actionDeploy(true),
            'delete' => self::actionDelete(),
            default => self::jsonResponse(['success' => false, 'error' => 'عملیات درخواست شده نامعتبر است.'], 400)
        };
    }

    /**
     * Handle GET actions.
     */
    private static function handleGetRequest(string $action): void
    {
        if (!Auth::isAuthenticated()) {
            self::renderLoginView();
            return;
        }

        match ($action) {
            'sites' => self::actionListSites(),
            'logout' => self::actionLogout(),
            default => self::renderDashboardView()
        };
    }

    /**
     * Process Admin Login.
     */
    private static function actionLogin(): void
    {
        $password = (string)($_POST['password'] ?? '');
        $isAjax = self::isAjaxRequest();

        if ($password === '') {
            $msg = 'لطفاً رمز عبور را وارد کنید.';
            if ($isAjax) {
                self::jsonResponse(['success' => false, 'error' => $msg], 422);
            } else {
                self::renderLoginView($msg);
            }
            return;
        }

        $result = Auth::attemptLogin($password, self::$config, self::$dataPath);

        if ($result['success']) {
            self::logAction('ورود موفق مدیر از آی‌پی: ' . Auth::getClientIp());
            if ($isAjax) {
                self::jsonResponse(['success' => true, 'redirect' => './']);
            } else {
                header('Location: ./');
                exit;
            }
        } else {
            self::logAction('تلاش ناموفق برای ورود از آی‌پی: ' . Auth::getClientIp());
            if ($isAjax) {
                self::jsonResponse(['success' => false, 'error' => $result['error']], 401);
            } else {
                self::renderLoginView($result['error']);
            }
        }
    }

    /**
     * Process Admin Logout.
     */
    private static function actionLogout(): void
    {
        self::logAction('خروج از پنل مدیریت از آی‌پی: ' . Auth::getClientIp());
        Auth::logout();
        header('Location: ./');
        exit;
    }

    /**
     * Process Deploy or Update.
     */
    private static function actionDeploy(bool $isUpdate): void
    {
        $adminSlug = self::$config['admin_slug'] ?? null;
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $name = Security::sanitizeText((string)($_POST['name'] ?? ''));

        // Validate slug
        $valSlug = Security::validateSlug($slug, $adminSlug);
        if (!$valSlug['valid']) {
            self::jsonResponse(['success' => false, 'error' => $valSlug['error']], 422);
            return;
        }

        if ($name === '') {
            $name = $slug;
        }

        $siteManager = new SiteManager(self::$dataPath, self::$publicHtmlPath);
        $existing = $siteManager->get($slug);

        if (!$isUpdate && $existing !== null) {
            self::jsonResponse(['success' => false, 'error' => 'سایتی با این شناسه مسیر در حال حاضر ثبت شده است.'], 422);
            return;
        }

        if ($isUpdate && $existing === null) {
            self::jsonResponse(['success' => false, 'error' => 'سایت مورد نظر برای به‌روزرسانی یافت نشد.'], 404);
            return;
        }

        if (!isset($_FILES['zip_file'])) {
            self::jsonResponse(['success' => false, 'error' => 'هیچ فایل ZIP انتخاب نشده است.'], 422);
            return;
        }

        $maxUpload = (int)(self::$config['max_upload_bytes'] ?? 104857600);
        $maxExtract = (int)(self::$config['max_extract_bytes'] ?? 314572800);
        $maxFiles = (int)(self::$config['max_file_count'] ?? 5000);

        $deployer = new Deployer(self::$publicHtmlPath, self::$dataPath, $maxUpload, $maxExtract, $maxFiles);
        $result = $deployer->deploy($_FILES['zip_file'], $slug, $isUpdate);

        if (!$result['success']) {
            self::jsonResponse(['success' => false, 'error' => $result['message']], 400);
            return;
        }

        $stats = $result['stats'] ?? [];
        $siteData = [
            'name' => $name,
            'slug' => $slug,
            'type' => $stats['type'] ?? 'static',
            'has_php' => $stats['has_php'] ?? false,
            'index_file' => $stats['index_file'] ?? null,
            'file_count' => $stats['file_count'] ?? 0,
            'total_size' => $stats['total_size'] ?? 0,
            'formatted_size' => $stats['formatted_size'] ?? '0 B',
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
            'last_deploy_at' => date('Y-m-d H:i:s')
        ];

        $siteManager->save($siteData);
        self::logAction(($isUpdate ? 'به‌روزرسانی' : 'دیپلوی') . " سایت '{$slug}' ({$name})");

        self::jsonResponse([
            'success' => true,
            'message' => $result['message'],
            'site' => $siteManager->get($slug)
        ]);
    }

    /**
     * Process Delete Site.
     */
    private static function actionDelete(): void
    {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));

        if ($slug === '') {
            self::jsonResponse(['success' => false, 'error' => 'شناسه سایت ارسال نشده است.'], 422);
            return;
        }

        $siteManager = new SiteManager(self::$dataPath, self::$publicHtmlPath);
        $existing = $siteManager->get($slug);

        if ($existing === null) {
            self::jsonResponse(['success' => false, 'error' => 'سایت مورد نظر یافت نشد.'], 404);
            return;
        }

        $deployer = new Deployer(self::$publicHtmlPath, self::$dataPath);
        $removed = $deployer->removeSiteDirectory($slug);

        if (!$removed) {
            self::jsonResponse(['success' => false, 'error' => 'خطا در حذف پوشه فایل‌های سایت از هاست.'], 500);
            return;
        }

        $siteManager->delete($slug);
        self::logAction("حذف سایت '{$slug}'");

        self::jsonResponse([
            'success' => true,
            'message' => "سایت '{$slug}' با موفقیت حذف شد."
        ]);
    }

    /**
     * List all sites as JSON.
     */
    private static function actionListSites(): void
    {
        $siteManager = new SiteManager(self::$dataPath, self::$publicHtmlPath);
        $sites = array_values($siteManager->getAll());
        self::jsonResponse(['success' => true, 'sites' => $sites]);
    }

    /**
     * Render Login View.
     */
    private static function renderLoginView(?string $error = null): void
    {
        $rate = Auth::checkRateLimit(self::$dataPath);
        require __DIR__ . '/Views/login.php';
    }

    /**
     * Render Dashboard View.
     */
    private static function renderDashboardView(): void
    {
        $siteManager = new SiteManager(self::$dataPath, self::$publicHtmlPath);
        $sites = array_values($siteManager->getAll());
        $csrfToken = Auth::getCsrfToken();
        $adminSlug = self::$config['admin_slug'] ?? 'panel';
        $maxUpload = SiteManager::formatBytes((int)(self::$config['max_upload_bytes'] ?? 104857600));

        require __DIR__ . '/Views/dashboard.php';
    }

    /**
     * Render fatal error.
     */
    private static function renderError(string $message): void
    {
        require __DIR__ . '/Views/error.php';
    }

    /**
     * Send JSON response.
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Check if request is AJAX.
     */
    private static function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }

    /**
     * Write an audit log entry.
     */
    private static function logAction(string $message): void
    {
        $logFile = self::$dataPath . DIRECTORY_SEPARATOR . 'deploy_logs.log';
        $time = date('Y-m-d H:i:s');
        $ip = Auth::getClientIp();
        $line = "[{$time}] [{$ip}] {$message}\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
