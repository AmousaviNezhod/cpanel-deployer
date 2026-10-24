<?php
declare(strict_types=1);

namespace Panel;

final class SiteManager
{
    private string $dataFile;
    private string $publicHtmlPath;

    public function __construct(string $dataPath, string $publicHtmlPath)
    {
        $this->dataFile = $dataPath . '/sites.json';
        $this->publicHtmlPath = rtrim($publicHtmlPath, '/\\');
    }

    /**
     * Get all registered sites with updated filesystem stats.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sites = $this->readSitesFile();
        $enriched = [];

        foreach ($sites as $slug => $site) {
            $enriched[$slug] = $this->refreshStats($site);
        }

        return $enriched;
    }

    /**
     * Get a single site by slug.
     */
    public function get(string $slug): ?array
    {
        $sites = $this->readSitesFile();
        if (!isset($sites[$slug])) {
            return null;
        }

        return $this->refreshStats($sites[$slug]);
    }

    /**
     * Save (create or update) a site entry in sites.json.
     */
    public function save(array $siteData): bool
    {
        $slug = $siteData['slug'] ?? '';
        if ($slug === '') {
            return false;
        }

        $sites = $this->readSitesFile();
        $sites[$slug] = array_merge($sites[$slug] ?? [], $siteData, [
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->writeSitesFile($sites);
    }

    /**
     * Delete a site from sites.json.
     */
    public function delete(string $slug): bool
    {
        $sites = $this->readSitesFile();
        if (!isset($sites[$slug])) {
            return false;
        }

        unset($sites[$slug]);
        return $this->writeSitesFile($sites);
    }

    /**
     * Inspect site filesystem directory and refresh stats.
     */
    public function refreshStats(array $site): array
    {
        $slug = $site['slug'] ?? '';
        $siteDir = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($siteDir)) {
            $site['exists'] = false;
            $site['status'] = 'missing_folder';
            $site['file_count'] = 0;
            $site['total_size'] = 0;
            $site['formatted_size'] = '0 B';
            $site['index_file'] = null;
            return $site;
        }

        $site['exists'] = true;
        $stats = $this->scanDirectoryStats($siteDir);

        $site['status'] = $stats['has_index'] ? 'active' : 'no_index';
        $site['file_count'] = $stats['file_count'];
        $site['total_size'] = $stats['total_size'];
        $site['formatted_size'] = $this->formatBytes($stats['total_size']);
        $site['index_file'] = $stats['index_file'];
        $site['has_php'] = $stats['has_php'];
        $site['type'] = $stats['has_php'] ? 'php' : 'static';

        return $site;
    }

    /**
     * Recursively scan directory for file count, size, and entry points.
     */
    private function scanDirectoryStats(string $dir): array
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
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
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
            // Silently handle read errors
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'has_index' => $indexFile !== null,
            'index_file' => $indexFile,
            'has_php' => $hasPhp
        ];
    }

    /**
     * Format bytes into human-readable string.
     */
    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Read sites from JSON file.
     */
    private function readSitesFile(): array
    {
        if (!file_exists($this->dataFile)) {
            return [];
        }

        $fp = @fopen($this->dataFile, 'rb');
        if (!$fp) {
            return [];
        }

        @flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        if ($content === false || trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write sites to JSON file atomically with exclusive lock.
     */
    private function writeSitesFile(array $sites): bool
    {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $fp = @fopen($this->dataFile, 'cb');
        if (!$fp) {
            return false;
        }

        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $json);
        fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        @chmod($this->dataFile, 0644);
        return $written !== false;
    }
}
