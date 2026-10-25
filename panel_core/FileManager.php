<?php
declare(strict_types=1);

namespace Panel;

/**
 * Service & Project File Manager.
 *
 * Provides safe, sandboxed directory browsing, code editing, file creation,
 * deletion, renaming, and uploading scoped strictly within each deployed site.
 */
class FileManager
{
    private string $publicHtmlPath;
    private string $dataPath;

    /** Allowed text extensions for online browser editing */
    private const TEXT_EXTENSIONS = [
        'html', 'htm', 'php', 'phtml', 'css', 'scss', 'sass', 'less',
        'js', 'mjs', 'cjs', 'ts', 'json', 'xml', 'svg', 'sql',
        'txt', 'md', 'markdown', 'htaccess', 'env', 'yml', 'yaml',
        'ini', 'conf', 'config', 'log', 'sh', 'bat'
    ];

    /** Image extensions for online browser preview */
    private const IMAGE_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg', 'bmp'
    ];

    public function __construct(string $publicHtmlPath, string $dataPath)
    {
        $this->publicHtmlPath = rtrim($publicHtmlPath, '\\/');
        $this->dataPath = rtrim($dataPath, '\\/');
    }

    /**
     * Resolve site root directory safely.
     */
    private function resolveSiteRoot(string $slug): ?string
    {
        $adminSlug = null; // validated against reserved
        $val = Security::validateSlug($slug, $adminSlug);
        if (!$val['valid']) {
            return null;
        }

        $sitePath = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($sitePath)) {
            return null;
        }

        $realSite = realpath($sitePath);
        $realPublic = realpath($this->publicHtmlPath);

        if ($realSite === false || $realPublic === false) {
            return null;
        }

        if (!Security::isPathWithin($realSite, $realPublic)) {
            return null;
        }

        return $realSite;
    }

    /**
     * Resolve relative target path within site root safely.
     */
    private function resolveSafePath(string $siteRoot, string $subpath): ?string
    {
        // Normalize slashes
        $subpath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($subpath, "\\/ \t\n\r\0\x0B"));

        // Reject directory traversal attempts in input
        if (str_contains($subpath, '..')) {
            return null;
        }

        if ($subpath === '') {
            return $siteRoot;
        }

        $target = $siteRoot . DIRECTORY_SEPARATOR . $subpath;

        // If path already exists, verify with realpath
        if (file_exists($target)) {
            $realTarget = realpath($target);
            if ($realTarget === false || !Security::isPathWithin($realTarget, $siteRoot)) {
                return null;
            }
            return $realTarget;
        }

        // For new items not yet created, ensure ancestor is within site root
        $parent = dirname($target);
        $realParent = realpath($parent);
        if ($realParent === false || !Security::isPathWithin($realParent, $siteRoot)) {
            return null;
        }

        return $target;
    }

    /**
     * List files and folders in a site directory.
     */
    public function listDirectory(string $slug, string $subpath = ''): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد یا دسترسی نامعتبر است.'];
        }

        $targetDir = $this->resolveSafePath($siteRoot, $subpath);
        if ($targetDir === null || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'پوشه مورد نظر یافت نشد یا نامعتبر است.'];
        }

        $cleanSub = trim(str_replace('\\', '/', substr($targetDir, strlen($siteRoot))), '/');
        $breadcrumbs = $this->buildBreadcrumbs($slug, $cleanSub);

        $parentPath = null;
        if ($cleanSub !== '') {
            $parentDir = dirname($cleanSub);
            $parentPath = ($parentDir === '.' || $parentDir === '') ? '' : str_replace('\\', '/', $parentDir);
        }

        $entries = @scandir($targetDir);
        if ($entries === false) {
            return ['success' => false, 'error' => 'خطا در خواندن محتوای پوشه.'];
        }

        $folders = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $itemPath = $targetDir . DIRECTORY_SEPARATOR . $entry;
            $relItem = ($cleanSub === '' ? '' : $cleanSub . '/') . $entry;
            $isDir = is_dir($itemPath);
            $mtime = @filemtime($itemPath);
            $mtimeFormatted = $mtime ? date('Y-m-d H:i:s', $mtime) : '-';

            if ($isDir) {
                $folders[] = [
                    'name' => $entry,
                    'path' => $relItem,
                    'type' => 'dir',
                    'mtime' => $mtimeFormatted,
                    'is_writable' => is_writable($itemPath)
                ];
            } else {
                $size = (int)@filesize($itemPath);
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if ($entry === '.htaccess') {
                    $ext = 'htaccess';
                }

                $isText = in_array($ext, self::TEXT_EXTENSIONS, true);
                $isImage = in_array($ext, self::IMAGE_EXTENSIONS, true);

                $files[] = [
                    'name' => $entry,
                    'path' => $relItem,
                    'type' => 'file',
                    'size' => $size,
                    'formatted_size' => SiteManager::formatBytes($size),
                    'ext' => $ext,
                    'mtime' => $mtimeFormatted,
                    'is_text' => $isText,
                    'is_image' => $isImage,
                    'is_writable' => is_writable($itemPath)
                ];
            }
        }

        // Sort folders alphabetically, files alphabetically
        usort($folders, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return [
            'success' => true,
            'slug' => $slug,
            'current_path' => $cleanSub,
            'parent_path' => $parentPath,
            'breadcrumbs' => $breadcrumbs,
            'folders' => $folders,
            'files' => $files,
            'total_items' => count($folders) + count($files)
        ];
    }

    /**
     * Read text file contents for browser code editor.
     */
    public function readFile(string $slug, string $filePath): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $target = $this->resolveSafePath($siteRoot, $filePath);
        if ($target === null || !is_file($target)) {
            return ['success' => false, 'error' => 'فایل مورد نظر یافت نشد.'];
        }

        $size = (int)@filesize($target);
        // Cap max file size for editor at 2 MB
        if ($size > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'حجم فایل بیش از ۲ مگابایت است و امکان باز کردن مستقیم در ویرایشگر مرورگر وجود ندارد.'];
        }

        $content = @file_get_contents($target);
        if ($content === false) {
            return ['success' => false, 'error' => 'خطا در خواندن محتوای فایل.'];
        }

        $filename = basename($target);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($filename === '.htaccess') {
            $ext = 'htaccess';
        }

        $syntax = $this->detectSyntax($ext);

        return [
            'success' => true,
            'slug' => $slug,
            'path' => str_replace('\\', '/', substr($target, strlen($siteRoot) + 1)),
            'filename' => $filename,
            'content' => $content,
            'size' => $size,
            'formatted_size' => SiteManager::formatBytes($size),
            'syntax' => $syntax,
            'is_writable' => is_writable($target),
            'mtime' => date('Y-m-d H:i:s', (int)@filemtime($target))
        ];
    }

    /**
     * Save edited file content.
     */
    public function saveFile(string $slug, string $filePath, string $content): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $target = $this->resolveSafePath($siteRoot, $filePath);
        if ($target === null || !is_file($target)) {
            return ['success' => false, 'error' => 'فایل مورد نظر یافت نشد.'];
        }

        if (!is_writable($target)) {
            return ['success' => false, 'error' => 'مجوز نوشتن روی این فایل وجود ندارد.'];
        }

        $res = @file_put_contents($target, $content, LOCK_EX);
        if ($res === false) {
            return ['success' => false, 'error' => 'خطا در ذخیره‌سازی فایل.'];
        }

        // Set safe permission
        @chmod($target, 0644);

        return [
            'success' => true,
            'message' => 'فایل با موفقیت ذخیره شد.',
            'size' => strlen($content),
            'formatted_size' => SiteManager::formatBytes(strlen($content)),
            'mtime' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Create a new empty file.
     */
    public function createFile(string $slug, string $subpath, string $filename): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $filename = trim($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $filename) || $filename === '.' || $filename === '..') {
            return ['success' => false, 'error' => 'نام فایل نامعتبر است (تنها حروف انگلیسی، ارقام، نقطه، خط تیره و زیرخط مجاز است).'];
        }

        $targetDir = $this->resolveSafePath($siteRoot, $subpath);
        if ($targetDir === null || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'پوشه مقصد نامعتبر است.'];
        }

        $newFilePath = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($newFilePath)) {
            return ['success' => false, 'error' => 'فایلی با این نام در این مسیر وجود دارد.'];
        }

        if (@file_put_contents($newFilePath, '', LOCK_EX) === false) {
            return ['success' => false, 'error' => 'خطا در ایجاد فایل.'];
        }

        @chmod($newFilePath, 0644);

        return [
            'success' => true,
            'message' => 'فایل جدید با موفقیت ایجاد شد.',
            'filename' => $filename
        ];
    }

    /**
     * Create a new directory.
     */
    public function createDirectory(string $slug, string $subpath, string $dirname): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $dirname = trim($dirname);
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $dirname) || $dirname === '.' || $dirname === '..') {
            return ['success' => false, 'error' => 'نام پوشه نامعتبر است.'];
        }

        $targetDir = $this->resolveSafePath($siteRoot, $subpath);
        if ($targetDir === null || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'پوشه مقصد نامعتبر است.'];
        }

        $newDirPath = $targetDir . DIRECTORY_SEPARATOR . $dirname;
        if (file_exists($newDirPath)) {
            return ['success' => false, 'error' => 'پوشه‌ای با این نام در این مسیر وجود دارد.'];
        }

        if (!@mkdir($newDirPath, 0755, true)) {
            return ['success' => false, 'error' => 'خطا در ایجاد پوشه جدید.'];
        }

        return [
            'success' => true,
            'message' => 'پوشه جدید با موفقیت ایجاد شد.',
            'dirname' => $dirname
        ];
    }

    /**
     * Rename a file or folder.
     */
    public function renameItem(string $slug, string $oldPath, string $newName): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $oldTarget = $this->resolveSafePath($siteRoot, $oldPath);
        if ($oldTarget === null || !file_exists($oldTarget)) {
            return ['success' => false, 'error' => 'مورد مورد نظر برای تغییر نام یافت نشد.'];
        }

        // Cannot rename site root itself
        if ($oldTarget === $siteRoot) {
            return ['success' => false, 'error' => 'تغییر نام ریشه سایت مجاز نیست.'];
        }

        $newName = trim($newName);
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $newName) || $newName === '.' || $newName === '..') {
            return ['success' => false, 'error' => 'نام جدید نامعتبر است.'];
        }

        $parentDir = dirname($oldTarget);
        $newTarget = $parentDir . DIRECTORY_SEPARATOR . $newName;

        if (file_exists($newTarget)) {
            return ['success' => false, 'error' => 'موردی با این نام از قبل وجود دارد.'];
        }

        if (!@rename($oldTarget, $newTarget)) {
            return ['success' => false, 'error' => 'خطا در تغییر نام.'];
        }

        return [
            'success' => true,
            'message' => 'تغییر نام با موفقیت انجام شد.',
            'new_name' => $newName
        ];
    }

    /**
     * Delete a file or directory recursively.
     */
    public function deleteItem(string $slug, string $itemPath): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $target = $this->resolveSafePath($siteRoot, $itemPath);
        if ($target === null || !file_exists($target)) {
            return ['success' => false, 'error' => 'مورد مورد نظر یافت نشد.'];
        }

        // Site root must NOT be deleted through file manager (use site delete action)
        if ($target === $siteRoot) {
            return ['success' => false, 'error' => 'حذف ریشه سایت از طریق مدیریت فایل مجاز نیست. از دکمه حذف سایت در داشبورد استفاده کنید.'];
        }

        if (is_dir($target)) {
            $deleted = Security::deleteDirectory($target);
        } else {
            $deleted = @unlink($target);
        }

        if (!$deleted) {
            return ['success' => false, 'error' => 'خطا در حذف مورد انتخاب‌شده.'];
        }

        return [
            'success' => true,
            'message' => 'مورد انتخاب‌شده با موفقیت حذف شد.'
        ];
    }

    /**
     * Upload a single asset file directly to the current directory.
     */
    public function uploadFile(string $slug, string $subpath, array $fileInfo): array
    {
        $siteRoot = $this->resolveSiteRoot($slug);
        if ($siteRoot === null) {
            return ['success' => false, 'error' => 'پوشه سایت یافت نشد.'];
        }

        $targetDir = $this->resolveSafePath($siteRoot, $subpath);
        if ($targetDir === null || !is_dir($targetDir)) {
            return ['success' => false, 'error' => 'پوشه مقصد نامعتبر است.'];
        }

        if (!isset($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return ['success' => false, 'error' => 'فایلی جهت آپلود ارسال نشده است.'];
        }

        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'خطا در ارسال فایل (کد ' . $fileInfo['error'] . ').'];
        }

        $origName = basename($fileInfo['name']);
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $origName) || $origName === '') {
            return ['success' => false, 'error' => 'نام فایل حاوی کاراکترهای نامجاز است.'];
        }

        $destination = $targetDir . DIRECTORY_SEPARATOR . $origName;
        if (!@move_uploaded_file($fileInfo['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'خطا در ذخیره‌سازی فایل در سرور.'];
        }

        @chmod($destination, 0644);

        return [
            'success' => true,
            'message' => "فایل '{$origName}' با موفقیت آپلود شد.",
            'filename' => $origName
        ];
    }

    /**
     * Build breadcrumbs array for folder navigation.
     */
    private function buildBreadcrumbs(string $slug, string $subpath): array
    {
        $crumbs = [
            ['name' => $slug, 'path' => '']
        ];

        if ($subpath === '') {
            return $crumbs;
        }

        $parts = explode('/', $subpath);
        $accum = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $accum = ($accum === '') ? $part : $accum . '/' . $part;
            $crumbs[] = [
                'name' => $part,
                'path' => $accum
            ];
        }

        return $crumbs;
    }

    /**
     * Map file extension to syntax identifier for editor.
     */
    private function detectSyntax(string $ext): string
    {
        return match ($ext) {
            'html', 'htm' => 'html',
            'php', 'phtml' => 'php',
            'css', 'scss', 'less' => 'css',
            'js', 'mjs', 'cjs', 'ts' => 'javascript',
            'json' => 'json',
            'xml', 'svg' => 'xml',
            'sql' => 'sql',
            'md', 'markdown' => 'markdown',
            default => 'plaintext'
        };
    }
}
