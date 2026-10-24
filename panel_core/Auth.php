<?php
declare(strict_types=1);

namespace Panel;

final class Auth
{
    private const SESSION_LIFETIME = 7200; // 2 hours
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes

    /**
     * Start secure session if not started.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = self::isHttps();

        if (!headers_sent()) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');

            @session_set_cookie_params([
                'lifetime' => self::SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            @session_name('CP_DEPLOYER_SESS');
        }

        @session_start();

        // Check session inactivity timeout
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > self::SESSION_LIFETIME) {
                self::logout();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Detect HTTPS including cPanel / Cloudflare reverse proxy headers.
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        return false;
    }

    /**
     * Get sanitized client IP.
     */
    public static function getClientIp(): string
    {
        $rawIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $filtered = filter_var($rawIp, FILTER_VALIDATE_IP);
        return $filtered !== false ? $filtered : '127.0.0.1';
    }

    /**
     * Check rate limiting for failed login attempts.
     *
     * @return array{allowed: bool, retry_after: int, remaining: int}
     */
    public static function checkRateLimit(string $dataPath): array
    {
        $ip = self::getClientIp();
        $rateFile = $dataPath . '/rate_limits.json';
        $data = self::readRateLimits($rateFile);
        $now = time();

        if (isset($data[$ip])) {
            $record = $data[$ip];
            $lockedUntil = $record['locked_until'] ?? 0;
            if ($lockedUntil > $now) {
                return [
                    'allowed' => false,
                    'retry_after' => $lockedUntil - $now,
                    'remaining' => 0
                ];
            }

            // Window check (15 mins)
            if ($now - $record['first_attempt'] > self::LOCKOUT_DURATION) {
                // Reset window
                unset($data[$ip]);
                self::writeRateLimits($rateFile, $data);
                return [
                    'allowed' => true,
                    'retry_after' => 0,
                    'remaining' => self::MAX_LOGIN_ATTEMPTS
                ];
            }

            $attempts = $record['attempts'] ?? 0;
            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = $now + self::LOCKOUT_DURATION;
                $data[$ip]['locked_until'] = $lockedUntil;
                self::writeRateLimits($rateFile, $data);
                return [
                    'allowed' => false,
                    'retry_after' => self::LOCKOUT_DURATION,
                    'remaining' => 0
                ];
            }

            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => max(0, self::MAX_LOGIN_ATTEMPTS - $attempts)
            ];
        }

        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => self::MAX_LOGIN_ATTEMPTS
        ];
    }

    /**
     * Record a failed login attempt.
     */
    public static function recordFailedLogin(string $dataPath): void
    {
        $ip = self::getClientIp();
        $rateFile = $dataPath . '/rate_limits.json';
        $data = self::readRateLimits($rateFile);
        $now = time();

        if (!isset($data[$ip]) || ($now - ($data[$ip]['first_attempt'] ?? 0) > self::LOCKOUT_DURATION)) {
            $data[$ip] = [
                'attempts' => 1,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        } else {
            $data[$ip]['attempts'] = ($data[$ip]['attempts'] ?? 0) + 1;
            if ($data[$ip]['attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
                $data[$ip]['locked_until'] = $now + self::LOCKOUT_DURATION;
            }
        }

        self::writeRateLimits($rateFile, $data);
    }

    /**
     * Reset failed attempts after successful login.
     */
    public static function resetRateLimit(string $dataPath): void
    {
        $ip = self::getClientIp();
        $rateFile = $dataPath . '/rate_limits.json';
        $data = self::readRateLimits($rateFile);
        if (isset($data[$ip])) {
            unset($data[$ip]);
            self::writeRateLimits($rateFile, $data);
        }
    }

    /**
     * Attempt login with password.
     *
     * @return array{success: bool, error: string}
     */
    public static function attemptLogin(string $password, array $config, string $dataPath): array
    {
        self::startSession();

        $rate = self::checkRateLimit($dataPath);
        if (!$rate['allowed']) {
            $minutes = ceil($rate['retry_after'] / 60);
            return [
                'success' => false,
                'error' => "تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً {$minutes} دقیقه دیگر مجدداً تلاش کنید."
            ];
        }

        $hash = $config['admin_password_hash'] ?? '';
        if ($hash === '' || !password_verify($password, $hash)) {
            self::recordFailedLogin($dataPath);
            $newRate = self::checkRateLimit($dataPath);
            $msg = 'رمز عبور وارد شده اشتباه است.';
            if ($newRate['remaining'] > 0) {
                $msg .= " ({$newRate['remaining']} تلاش باقی‌مانده)";
            } else {
                $msg .= " حساب برای ۱۵ دقیقه موقتاً قفل شد.";
            }
            return [
                'success' => false,
                'error' => $msg
            ];
        }

        // Success: regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['_last_activity'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        self::resetRateLimit($dataPath);
        self::getCsrfToken(); // Ensure token generated

        return [
            'success' => true,
            'error' => ''
        ];
    }

    /**
     * Check if current session is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        self::startSession();
        if (empty($_SESSION['authenticated']) || empty($_SESSION['admin_logged_in'])) {
            return false;
        }

        // Verify User Agent consistency
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentAgent) {
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * Log out and destroy session.
     */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('CP_DEPLOYER_SESS');
            @session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        @session_destroy();
    }

    /**
     * Get or create CSRF token.
     */
    public static function getCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Read rate limits JSON file safely.
     */
    private static function readRateLimits(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write rate limits JSON file with lock.
     */
    private static function writeRateLimits(string $filePath, array $data): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Purge expired records older than 1 hour to keep file compact
        $now = time();
        foreach ($data as $ip => $item) {
            if (($now - ($item['first_attempt'] ?? 0) > 3600) && ($item['locked_until'] ?? 0) < $now) {
                unset($data[$ip]);
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            @file_put_contents($filePath, $json, LOCK_EX);
            @chmod($filePath, 0644);
        }
    }
}
