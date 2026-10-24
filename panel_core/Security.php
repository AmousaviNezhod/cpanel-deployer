<?php
declare(strict_types=1);

namespace Panel;

final class Security
{
    /**
     * Default reserved slugs that cannot be used as site directories.
     */
    private const RESERVED_SLUGS = [
        'panel_core',
        'panel_data',
        'install.php',
        'install',
        'cgi-bin',
        '.well-known',
        '.trash',
        'public_html',
        'wp-admin',
        'wp-content',
        'wp-includes',
        'index.php',
        'index.html',
        'robots.txt',
        'favicon.ico',
        '.htaccess',
        'api',
        'admin',
        'administrator',
        'system',
        'core',
        'data',
        'config',
        'backup',
        'backups',
        'tmp',
        'temp'
    ];

    /**
     * Validate a site slug.
     * Allowed: 2-40 characters, lowercase letters, numbers, hyphens, underscores.
     * Must begin with an alphanumeric character.
     *
     * @param string $slug
     * @param string|null $adminSlug
     * @return array{valid: bool, error: string}
     */
    public static function validateSlug(string $slug, ?string $adminSlug = null): array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return ['valid' => false, 'error' => 'شناسه مسیر (Slug) نمی‌تواند خالی باشد.'];
        }

        if (strlen($slug) < 2 || strlen($slug) > 40) {
            return ['valid' => false, 'error' => 'طول شناسه مسیر باید بین ۲ تا ۴۰ کاراکتر باشد.'];
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
            return [
                'valid' => false,
                'error' => 'شناسه مسیر فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد، خط تیره (-) و زیرخط (_) باشد و باید با حرف یا عدد شروع شود.'
            ];
        }

        // Check reserved slugs
        $reserved = self::RESERVED_SLUGS;
        if ($adminSlug !== null && $adminSlug !== '') {
            $reserved[] = strtolower(trim($adminSlug));
        }

        if (in_array(strtolower($slug), $reserved, true)) {
            return ['valid' => false, 'error' => 'این شناسه مسیر رزرو شده یا مربوط به سیستم است و قابل استفاده نیست.'];
        }

        // Ensure slug is not a traversal attempt
        if (basename($slug) !== $slug || str_contains($slug, '..') || str_contains($slug, '/') || str_contains($slug, '\\')) {
            return ['valid' => false, 'error' => 'شناسه مسیر نامعتبر است.'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Normalize and sanitize a text string (e.g. site title).
     */
    public static function sanitizeText(string $text, int $maxLength = 100): string
    {
        $text = trim($text);
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        return $text;
    }

    /**
     * Recursively apply safe file and directory permissions.
     * Dirs: 0755, Files: 0644. Never 0777.
     */
    public static function setSafePermissions(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        // On Windows chmod has limited effect, but we still invoke it safely
        if (is_dir($path)) {
            @chmod($path, 0755);
            $items = @scandir($path);
            if ($items !== false) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    self::setSafePermissions($path . DIRECTORY_SEPARATOR . $item);
                }
            }
        } else {
            @chmod($path, 0644);
        }
    }

    /**
     * Verify that a target path is strictly contained within a base directory.
     */
    public static function isPathWithin(string $targetPath, string $baseDir): bool
    {
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            return false;
        }

        // If target exists, resolve realpath
        $realTarget = realpath($targetPath);
        if ($realTarget !== false) {
            return str_starts_with($realTarget, $realBase . DIRECTORY_SEPARATOR) || $realTarget === $realBase;
        }

        // If target doesn't exist yet, normalize target path
        $normalizedBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realBase), DIRECTORY_SEPARATOR);
        $normalizedTarget = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

        // Disallow '..' components
        $parts = explode(DIRECTORY_SEPARATOR, $normalizedTarget);
        $resolvedParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return false;
            }
            $resolvedParts[] = $part;
        }

        $resolved = implode(DIRECTORY_SEPARATOR, $resolvedParts);
        return str_starts_with($resolved, $normalizedBase . DIRECTORY_SEPARATOR);
    }

    /**
     * Delete directory recursively and safely.
     */
    public static function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $current = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($current)) {
                @unlink($current);
            } elseif (is_dir($current)) {
                self::deleteDirectory($current);
            } else {
                @unlink($current);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Generate a cryptographically secure random token.
     */
    public static function generateToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
