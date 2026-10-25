<?php
declare(strict_types=1);

namespace Panel;

final class SiteManager
{
    private string $dataPath;
    private string $dataFile;
    private string $publicHtmlPath;

    public function __construct(string $dataPath, string $publicHtmlPath)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->dataFile = $this->dataPath . '/sites.json';
        $this->publicHtmlPath = rtrim($publicHtmlPath, '/\\');
    }

    /**
     * Get all registered sites with updated filesystem stats and size percentages.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sites = $this->readSitesFile();
        $enriched = [];
        $totalBytes = 0;

        foreach ($sites as $slug => $site) {
            $refreshed = $this->refreshStats($site);
            $enriched[$slug] = $refreshed;
            $totalBytes += (int)($refreshed['total_size'] ?? 0);
        }

        // Calculate proportional percentage for each site
        foreach ($enriched as $slug => &$item) {
            $item['size_percent'] = ($totalBytes > 0) ? round(((int)$item['total_size'] / $totalBytes) * 100, 1) : 0;
        }
        unset($item);

        return $enriched;
    }

    /**
     * Get overall disk statistics for host and deployed services, including Deployer footprint.
     */
    public function getDiskStats(?int $customQuotaMb = null): array
    {
        $sites = $this->getAll();
        $servicesTotalBytes = 0;
        $totalFiles = 0;

        foreach ($sites as $site) {
            $servicesTotalBytes += (int)($site['total_size'] ?? 0);
            $totalFiles += (int)($site['file_count'] ?? 0);
        }

        // Measure panel Deployer core & data storage
        $deployerStorage = $this->getDeployerStorageSize();
        $totalAppUsedBytes = $servicesTotalBytes + $deployerStorage['bytes'];
        $totalAppFiles = $totalFiles + $deployerStorage['files'];

        // Physical filesystem partition (what statvfs reports)
        $partTotal = @disk_total_space($this->publicHtmlPath);
        $partFree = @disk_free_space($this->publicHtmlPath);
        $partUsed = ($partTotal !== false && $partFree !== false) ? max(0, $partTotal - $partFree) : 0;
        $partUsedPercent = ($partTotal && $partTotal > 0) ? round(($partUsed / $partTotal) * 100, 1) : 0;

        // Try detecting real cPanel account quota (e.g. 200 MB)
        $cpanelQuota = $this->detectCpanelQuota();

        $isAccountQuota = false;
        $quotaSource = 'partition';

        if ($cpanelQuota !== null && $cpanelQuota['total_bytes'] > 0) {
            $isAccountQuota = true;
            $quotaSource = 'cpanel_quota';
            $hostTotal = $cpanelQuota['total_bytes'];
            // If cPanel reported less than actual services + panel, use max
            $hostUsed = max($cpanelQuota['used_bytes'], $totalAppUsedBytes);
            $hostFree = max(0, $hostTotal - $hostUsed);
            $hostUsedPercent = round(($hostUsed / $hostTotal) * 100, 1);
        } elseif ($customQuotaMb !== null && $customQuotaMb > 0) {
            $isAccountQuota = true;
            $quotaSource = 'custom_config';
            $hostTotal = $customQuotaMb * 1024 * 1024;
            $hostUsed = $totalAppUsedBytes;
            $hostFree = max(0, $hostTotal - $hostUsed);
            $hostUsedPercent = round(($hostUsed / $hostTotal) * 100, 1);
        } else {
            $hostTotal = (int)($partTotal ?: 0);
            $hostUsed = (int)$partUsed;
            $hostFree = (int)($partFree ?: 0);
            $hostUsedPercent = $partUsedPercent;
        }

        return [
            'host' => [
                'is_account_quota' => $isAccountQuota,
                'quota_source' => $quotaSource,
                'total_bytes' => $hostTotal,
                'free_bytes' => $hostFree,
                'used_bytes' => $hostUsed,
                'total_formatted' => self::formatBytes($hostTotal),
                'free_formatted' => self::formatBytes($hostFree),
                'used_formatted' => self::formatBytes($hostUsed),
                'used_percent' => $hostUsedPercent,
                'partition_total_formatted' => $partTotal ? self::formatBytes((int)$partTotal) : 'نامشخص',
                'partition_free_formatted' => $partFree ? self::formatBytes((int)$partFree) : 'نامشخص'
            ],
            'deployer' => [
                'total_bytes' => $deployerStorage['bytes'],
                'total_formatted' => self::formatBytes($deployerStorage['bytes']),
                'total_files' => $deployerStorage['files'],
                'percent_of_quota' => $hostTotal > 0 ? round(($deployerStorage['bytes'] / $hostTotal) * 100, 2) : 0
            ],
            'services' => [
                'total_bytes' => $servicesTotalBytes,
                'total_formatted' => self::formatBytes($servicesTotalBytes),
                'total_files' => $totalFiles,
                'count' => count($sites),
                'sites' => array_values($sites),
                'percent_of_quota' => $hostTotal > 0 ? round(($servicesTotalBytes / $hostTotal) * 100, 2) : 0
            ],
            'total_managed' => [
                'total_bytes' => $totalAppUsedBytes,
                'total_formatted' => self::formatBytes($totalAppUsedBytes),
                'total_files' => $totalAppFiles,
                'percent_of_quota' => $hostTotal > 0 ? round(($totalAppUsedBytes / $hostTotal) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Calculate disk storage footprint of cPanel Deployer panel itself (panel_core + panel_data + root files).
     */
    public function getDeployerStorageSize(): array
    {
        $bytes = 0;
        $files = 0;

        $targets = [
            __DIR__, // panel_core
            $this->dataPath, // panel_data
        ];

        // Root entry files
        $rootFiles = ['index.php', 'install.php', 'README.md', '.htaccess', 'cpanel-deployer.zip'];
        foreach ($rootFiles as $rf) {
            $fpath = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $rf;
            if (is_file($fpath)) {
                $bytes += (int)@filesize($fpath);
                $files++;
            }
        }

        foreach ($targets as $targetDir) {
            if (!is_dir($targetDir)) {
                continue;
            }

            try {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($targetDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($it as $item) {
                    if ($item->isFile()) {
                        $bytes += (int)$item->getSize();
                        $files++;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return [
            'bytes' => $bytes,
            'formatted' => self::formatBytes($bytes),
            'files' => $files
        ];
    }

    /**
     * Detect real cPanel user account quota from ~/.cpanel/datastore/
     */
    private function detectCpanelQuota(): ?array
    {
        $userHome = dirname($this->publicHtmlPath);
        $datastoreDir = $userHome . DIRECTORY_SEPARATOR . '.cpanel' . DIRECTORY_SEPARATOR . 'datastore';

        if (!is_dir($datastoreDir)) {
            return null;
        }

        $files = @scandir($datastoreDir) ?: [];
        foreach ($files as $file) {
            if (str_starts_with($file, '_Cpanel::Quota.pm__')) {
                $fullPath = $datastoreDir . DIRECTORY_SEPARATOR . $file;
                $content = (string)@file_get_contents($fullPath);
                if ($content === '') {
                    continue;
                }

                $json = json_decode($content, true);
                if (isset($json['data']) && is_array($json['data']) && count($json['data']) >= 2) {
                    $used = (int)($json['data'][0] ?? 0);
                    $total = (int)($json['data'][1] ?? 0);

                    if ($total > 0) {
                        return [
                            'used_bytes' => $used,
                            'total_bytes' => $total
                        ];
                    }
                }
            }
        }

        return null;
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
