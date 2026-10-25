<?php
declare(strict_types=1);

namespace Panel;

final class Deployer
{
    private string $publicHtmlPath;
    private string $tmpPath;
    private int $maxUploadBytes;
    private int $maxExtractBytes;
    private int $maxFileCount;

    public function __construct(
        string $publicHtmlPath,
        string $dataPath,
        int $maxUploadBytes = 104857600,  // 100 MB
        int $maxExtractBytes = 314572800, // 300 MB
        int $maxFileCount = 5000
    ) {
        $this->publicHtmlPath = rtrim($publicHtmlPath, '/\\');
        $this->tmpPath = rtrim($dataPath, '/\\') . DIRECTORY_SEPARATOR . 'tmp';
        $this->maxUploadBytes = $maxUploadBytes;
        $this->maxExtractBytes = $maxExtractBytes;
        $this->maxFileCount = $maxFileCount;

        if (!is_dir($this->tmpPath)) {
            @mkdir($this->tmpPath, 0755, true);
        }
    }

    /**
     * Deploy a site from an uploaded ZIP file.
     *
     * @param array $fileInfo $_FILES['...'] array
     * @param string $slug Site directory slug
     * @param bool $isUpdate True if updating existing site
     * @param bool $fixRoutes True if adapting root routes for subpath
     * @return array{success: bool, message: string, stats?: array}
     */
    public function deploy(array $fileInfo, string $slug, bool $isUpdate = false, bool $fixRoutes = false): array
    {
        $targetDir = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $slug;

        // Verify target path safety
        if (!Security::isPathWithin($targetDir, $this->publicHtmlPath)) {
            return ['success' => false, 'message' => 'مسیر مقصد نامعتبر یا خارج از محدوده مجاز است.'];
        }

        if (!$isUpdate && is_dir($targetDir)) {
            return ['success' => false, 'message' => 'پوشه‌ای با این شناسه مسیر در حال حاضر وجود دارد.'];
        }

        if ($isUpdate && !is_dir($targetDir)) {
            return ['success' => false, 'message' => 'سایت مورد نظر برای به‌روزرسانی یافت نشد.'];
        }

        // Validate uploaded file
        $valResult = $this->validateUpload($fileInfo);
        if (!$valResult['valid']) {
            return ['success' => false, 'message' => $valResult['error']];
        }

        $uploadedZipPath = $fileInfo['tmp_name'];
        $stagingDir = $this->tmpPath . DIRECTORY_SEPARATOR . 'staging_' . uniqid('', true);
        $backupDir = $isUpdate ? ($this->tmpPath . DIRECTORY_SEPARATOR . 'backup_' . $slug . '_' . uniqid('', true)) : null;

        try {
            if (!@mkdir($stagingDir, 0755, true)) {
                return ['success' => false, 'message' => 'خطا در ایجاد پوشه موقت استخراج.'];
            }

            // Inspect and extract ZIP safely
            $extractResult = $this->safeExtract($uploadedZipPath, $stagingDir);
            if (!$extractResult['success']) {
                Security::deleteDirectory($stagingDir);
                return ['success' => false, 'message' => $extractResult['message']];
            }

            // Strip single root folder if detected
            $this->stripSingleRootFolder($stagingDir);

            // Adapt root-relative routes and inject <base href> if requested
            if ($fixRoutes) {
                $this->adaptSubpathRoutes($stagingDir, $slug);
            }

            // Apply safe permissions
            Security::setSafePermissions($stagingDir);

            // Verify entry points
            $stats = $this->inspectExtractedFiles($stagingDir);

            // Perform atomic replace / move
            if ($isUpdate && $backupDir !== null) {
                // Step 1: Move current to backup
                if (!@rename($targetDir, $backupDir)) {
                    Security::deleteDirectory($stagingDir);
                    return ['success' => false, 'message' => 'خطا در پشتیبان‌گیری موقت از نسخه فعلی سایت.'];
                }

                // Step 2: Move staging to target
                if (!@rename($stagingDir, $targetDir)) {
                    // Rollback
                    @rename($backupDir, $targetDir);
                    Security::deleteDirectory($stagingDir);
                    return ['success' => false, 'message' => 'خطا در جایگزینی فایل‌های جدید سایت. نسخه قبلی بازگردانی شد.'];
                }

                // Step 3: Clean up backup
                Security::deleteDirectory($backupDir);
            } else {
                // New deployment
                if (!@rename($stagingDir, $targetDir)) {
                    Security::deleteDirectory($stagingDir);
                    return ['success' => false, 'message' => 'خطا در انتقال فایل‌ها به پوشه نهایی سایت.'];
                }
            }

            return [
                'success' => true,
                'message' => $isUpdate ? 'سایت با موفقیت به‌روزرسانی شد.' : 'سایت با موفقیت دیپلوی شد.',
                'stats' => $stats
            ];
        } catch (\Throwable $e) {
            // Clean up staging
            if (is_dir($stagingDir)) {
                Security::deleteDirectory($stagingDir);
            }
            // Rollback backup if needed
            if ($isUpdate && $backupDir !== null && is_dir($backupDir) && !is_dir($targetDir)) {
                @rename($backupDir, $targetDir);
            }
            return [
                'success' => false,
                'message' => 'خطای سیستمی هنگام استخراج و دیپلوی: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete an existing deployed site directory.
     */
    public function removeSiteDirectory(string $slug): bool
    {
        $targetDir = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $slug;

        if (!Security::isPathWithin($targetDir, $this->publicHtmlPath)) {
            return false;
        }

        if (!is_dir($targetDir)) {
            return true; // Already gone
        }

        return Security::deleteDirectory($targetDir);
    }

    /**
     * Validate upload error, size, and ZIP header.
     *
     * @return array{valid: bool, error: string}
     */
    private function validateUpload(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $msg = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیشتر از سقف مجاز سرور است.',
                UPLOAD_ERR_PARTIAL => 'فایل به‌صورت ناقص آپلود شده است.',
                UPLOAD_ERR_NO_FILE => 'هیچ فایلی ارسال نشده است.',
                UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت آپلود در سرور یافت نشد.',
                UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل روی دیسک هاست.',
                default => 'خطای نامشخص در آپلود فایل.'
            };
            return ['valid' => false, 'error' => $msg];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return ['valid' => false, 'error' => 'فایل آپلود شده خالی است.'];
        }

        if ($size > $this->maxUploadBytes) {
            $formattedMax = SiteManager::formatBytes($this->maxUploadBytes);
            return ['valid' => false, 'error' => "حجم فایل بیشتر از سقف مجاز ({$formattedMax}) است."];
        }

        $tmpPath = $file['tmp_name'] ?? '';
        $isLocal = !empty($file['is_local']) || (php_sapi_name() === 'cli');
        if (!$isLocal && !is_uploaded_file($tmpPath)) {
            return ['valid' => false, 'error' => 'فایل ارسالی معتبر نیست.'];
        }
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return ['valid' => false, 'error' => 'امکان خواندن فایل ارسال شده وجود ندارد.'];
        }

        // Verify ZIP magic bytes (PK\x03\x04 or PK\x05\x06)
        $fh = @fopen($tmpPath, 'rb');
        if (!$fh) {
            return ['valid' => false, 'error' => 'امکان خواندن فایل آپلود شده وجود ندارد.'];
        }
        $header = fread($fh, 4);
        fclose($fh);

        if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06") {
            return ['valid' => false, 'error' => 'فرمت فایل معتبر نیست؛ لطفاً یک فایل ZIP استاندارد ارسال کنید.'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Safely extract ZIP contents with strict Zip Slip, symlink, and Bomb defenses.
     *
     * @return array{success: bool, message: string}
     */
    private function safeExtract(string $zipPath, string $destinationDir): array
    {
        $zip = new \ZipArchive();
        $status = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($status !== true) {
            return ['success' => false, 'message' => 'امکان باز کردن فایل ZIP وجود ندارد (کد خطا: ' . $status . ').'];
        }

        $numFiles = $zip->numFiles;
        if ($numFiles <= 0) {
            $zip->close();
            return ['success' => false, 'message' => 'فایل ZIP خالی است.'];
        }

        if ($numFiles > $this->maxFileCount) {
            $zip->close();
            return ['success' => false, 'message' => "تعداد فایل‌های داخل ZIP ({$numFiles}) بیش از سقف مجاز ({$this->maxFileCount}) است."];
        }

        $totalUncompressedBytes = 0;
        $realDest = realpath($destinationDir);
        if ($realDest === false) {
            $zip->close();
            return ['success' => false, 'message' => 'پوشه مقصد نامعتبر است.'];
        }

        // Pre-validation pass: check all entries for security risks
        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = $stat['name'];

            // 1. Check for null bytes, leading slashes, path traversal
            if (str_contains($entryName, "\0") ||
                str_contains($entryName, '..') ||
                str_starts_with($entryName, '/') ||
                str_starts_with($entryName, '\\') ||
                preg_match('/^[a-zA-Z]:/', $entryName) === 1
            ) {
                $zip->close();
                return ['success' => false, 'message' => 'فایل ZIP حاوی مسیرهای خطرناک یا نامعتبر است (Zip Slip Detected).'];
            }

            // 2. Symlink detection via POSIX attributes
            $opsys = 0;
            $attr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr)) {
                if ($opsys === \ZipArchive::OPSYS_UNIX) {
                    $mode = ($attr >> 16) & 0177777;
                    if (($mode & 0120000) === 0120000) {
                        $zip->close();
                        return ['success' => false, 'message' => 'فایل ZIP حاوی پیوند نمادین (Symlink) است که به دلایل امنیتی مجاز نیست.'];
                    }
                }
            }

            // 3. Accumulate uncompressed size
            $size = (int)$stat['size'];
            $totalUncompressedBytes += $size;
            if ($totalUncompressedBytes > $this->maxExtractBytes) {
                $zip->close();
                $formattedMax = SiteManager::formatBytes($this->maxExtractBytes);
                return ['success' => false, 'message' => "حجم محتوای استخراج‌شده از سقف مجاز ({$formattedMax}) فراتر می‌رود (Zip Bomb Protection)."];
            }
        }

        // Extraction pass
        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = $stat['name'];
            $targetPath = $destinationDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryName);

            // Double check resolved destination
            if (!Security::isPathWithin($targetPath, $destinationDir)) {
                $zip->close();
                return ['success' => false, 'message' => 'مسیر استخراج خارج از پوشه مقصد قرار دارد.'];
            }

            // Is directory entry
            if (str_ends_with($entryName, '/') || str_ends_with($entryName, '\\')) {
                if (!is_dir($targetPath) && !@mkdir($targetPath, 0755, true)) {
                    $zip->close();
                    return ['success' => false, 'message' => "خطا در ایجاد پوشه: {$entryName}"];
                }
                continue;
            }

            // Ensure parent directory exists
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir) && !@mkdir($parentDir, 0755, true)) {
                $zip->close();
                return ['success' => false, 'message' => "خطا در ایجاد پوشه والد برای: {$entryName}"];
            }

            // Extract file content
            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                $zip->close();
                return ['success' => false, 'message' => "خطا در خواندن فایل از آرشیو: {$entryName}"];
            }

            $outHandle = @fopen($targetPath, 'wb');
            if ($outHandle === false) {
                fclose($stream);
                $zip->close();
                return ['success' => false, 'message' => "خطا در نوشتن فایل استخراج‌شده: {$entryName}"];
            }

            stream_copy_to_stream($stream, $outHandle);
            fclose($stream);
            fclose($outHandle);

            @chmod($targetPath, 0644);
        }

        $zip->close();
        return ['success' => true, 'message' => 'استخراج با موفقیت انجام شد.'];
    }

    /**
     * If all extracted items are wrapped in a single root folder, strip it.
     */
    private function stripSingleRootFolder(string $dir): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        $filtered = array_values(array_filter($items, fn($x) => $x !== '.' && $x !== '..'));

        // If exactly one directory exists and no other files exist in root
        if (count($filtered) === 1) {
            $singleSubdir = $dir . DIRECTORY_SEPARATOR . $filtered[0];
            if (is_dir($singleSubdir)) {
                // Move all children of singleSubdir to dir
                $subItems = @scandir($singleSubdir);
                if ($subItems !== false) {
                    $tempDir = $dir . '_unwrap_' . uniqid();
                    if (@rename($singleSubdir, $tempDir)) {
                        $movedItems = @scandir($tempDir);
                        if ($movedItems !== false) {
                            foreach ($movedItems as $item) {
                                if ($item === '.' || $item === '..') {
                                    continue;
                                }
                                @rename(
                                    $tempDir . DIRECTORY_SEPARATOR . $item,
                                    $dir . DIRECTORY_SEPARATOR . $item
                                );
                            }
                        }
                        @rmdir($tempDir);
                    }
                }
            }
        }
    }

    /**
     * Inspect extracted files for stats and primary entry point.
     */
    private function inspectExtractedFiles(string $dir): array
    {
        $fileCount = 0;
        $totalSize = 0;
        $hasPhp = false;
        $indexFile = null;

        $checkIndexes = ['index.php', 'index.html', 'index.htm'];
        foreach ($checkIndexes as $idx) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $idx)) {
                $indexFile = $idx;
                break;
            }
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $fileCount++;
                    $totalSize += $item->getSize();
                    $ext = strtolower($item->getExtension());
                    if ($ext === 'php' || $ext === 'phtml' || $ext === 'php8') {
                        $hasPhp = true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'formatted_size' => SiteManager::formatBytes($totalSize),
            'has_index' => $indexFile !== null,
            'index_file' => $indexFile,
            'has_php' => $hasPhp,
            'type' => $hasPhp ? 'php' : 'static'
        ];
    }

    /**
     * Adapt root-relative routes, inject per-directory <base href>, and configure RewriteBase for subpaths.
     */
    private function adaptSubpathRoutes(string $dir, string $slug): void
    {
        $escapedSlug = preg_quote($slug, '/');
        $hasHtaccess = false;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $filename = $item->getFilename();
                $ext = strtolower($item->getExtension());
                $path = $item->getPathname();

                // 1. Process HTML / PHP templates
                if (in_array($ext, ['html', 'htm', 'php', 'phtml'], true)) {
                    $content = @file_get_contents($path);
                    if ($content === false || $content === '') {
                        continue;
                    }

                    $changed = false;

                    // Calculate accurate base href relative to file directory depth
                    $relPath = substr($path, strlen($dir) + 1);
                    $relDir = dirname($relPath);
                    $fileBase = ($relDir === '.' || $relDir === '')
                        ? "/{$slug}/"
                        : "/{$slug}/" . trim(str_replace('\\', '/', $relDir), '/') . "/";

                    // Inject or update <base href="...">
                    if (preg_match('/<base\s[^>]*href=[\'"][^\'"]*[\'"][^>]*>/i', $content)) {
                        $content = preg_replace('/<base\s[^>]*href=[\'"][^\'"]*[\'"][^>]*>/i', '<base href="' . $fileBase . '">', $content);
                        $changed = true;
                    } elseif (preg_match('/<head(\s[^>]*)?>/i', $content)) {
                        $content = preg_replace('/<head(\s[^>]*)?>/i', "$0\n    <base href=\"{$fileBase}\">", $content, 1);
                        $changed = true;
                    }

                    // Fix root-absolute paths in href, src, action (starting with /)
                    $pattern = '/\b(href|src|action)=([\'"])\/(?!\/|' . $escapedSlug . '\/)([^\'"]*)\2/i';
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '$1=$2/' . $slug . '/$3$2', $content);
                        $changed = true;
                    }

                    if ($changed) {
                        @file_put_contents($path, $content);
                    }
                }

                // 2. Process CSS files
                if ($ext === 'css') {
                    $content = @file_get_contents($path);
                    if ($content === false || $content === '') {
                        continue;
                    }

                    $cssPattern = '/url\(\s*([\'"]?)\/(?!\/|' . $escapedSlug . '\/)(.*?)\1\s*\)/i';
                    if (preg_match($cssPattern, $content)) {
                        $content = preg_replace($cssPattern, 'url($1/' . $slug . '/$2$1)', $content);
                        @file_put_contents($path, $content);
                    }
                }

                // 3. Process .htaccess
                if ($filename === '.htaccess' && dirname($path) === $dir) {
                    $hasHtaccess = true;
                    $content = @file_get_contents($path);
                    if ($content !== false && $content !== '') {
                        if (preg_match('/RewriteBase\s+[^\r\n]+/i', $content)) {
                            $content = preg_replace('/RewriteBase\s+[^\r\n]+/i', 'RewriteBase /' . $slug . '/', $content);
                        } elseif (preg_match('/RewriteEngine\s+On/i', $content)) {
                            $content = preg_replace('/RewriteEngine\s+On/i', "RewriteEngine On\n    RewriteBase /{$slug}/", $content, 1);
                        }
                        @file_put_contents($path, $content);
                    }
                }
            }

            // If no root .htaccess exists, create one with RewriteBase and DirectorySlash protection
            if (!$hasHtaccess) {
                $defaultHtaccess = "<IfModule mod_rewrite.c>\n"
                    . "    RewriteEngine On\n"
                    . "    RewriteBase /{$slug}/\n"
                    . "    # Redirect directory requests without trailing slash\n"
                    . "    RewriteCond %{REQUEST_FILENAME} -d\n"
                    . "    RewriteCond %{REQUEST_URI} !(.*)/$\n"
                    . "    RewriteRule ^(.*)$ $1/ [L,R=301]\n"
                    . "</IfModule>\n";
                @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', $defaultHtaccess);
            }

            // 4. Resolve missing HTML file aliases and case mismatches
            $this->createMissingHtmlAliases($dir);

        } catch (\Throwable $e) {
            // Silently ignore traversal issues
        }
    }

    /**
     * Scan directory for links to missing HTML files and create fallback aliases (e.g. about.html -> aboutUs.html).
     */
    private function createMissingHtmlAliases(string $dir): void
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $ext = strtolower($item->getExtension());
                if (!in_array($ext, ['html', 'htm', 'php'], true)) {
                    continue;
                }

                $content = @file_get_contents($item->getPathname());
                if (!$content) {
                    continue;
                }

                $fileDir = $item->getPath();

                // Find relative links like href="about.html" or href="houses.html"
                if (preg_match_all('/\bhref=[\'"]([a-zA-Z0-9_\-]+\.html?)[\'"]/i', $content, $m)) {
                    $dirFiles = scandir($fileDir);
                    if (!$dirFiles) {
                        continue;
                    }
                    $mapLower = [];
                    foreach ($dirFiles as $df) {
                        if ($df === '.' || $df === '..') {
                            continue;
                        }
                        $mapLower[strtolower($df)] = $df;
                    }

                    foreach ($m[1] as $target) {
                        $targetPath = $fileDir . DIRECTORY_SEPARATOR . $target;
                        if (file_exists($targetPath)) {
                            continue;
                        }

                        // Case-insensitive match (e.g. houses.html vs Houses.html)
                        $tLower = strtolower($target);
                        if (isset($mapLower[$tLower])) {
                            @copy($fileDir . DIRECTORY_SEPARATOR . $mapLower[$tLower], $targetPath);
                            continue;
                        }

                        // Us-variant match (e.g. about.html vs aboutUs.html, contact.html vs contactUs.html)
                        $baseName = pathinfo($target, PATHINFO_FILENAME);
                        $tExt = pathinfo($target, PATHINFO_EXTENSION);
                        $usVariant = strtolower($baseName . 'us.' . $tExt);
                        if (isset($mapLower[$usVariant])) {
                            @copy($fileDir . DIRECTORY_SEPARATOR . $mapLower[$usVariant], $targetPath);
                            continue;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
