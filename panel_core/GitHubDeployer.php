<?php
declare(strict_types=1);

namespace Panel;

/**
 * GitHub Repository Importer & Continuous Deployer.
 *
 * Allows directly cloning/fetching public and private GitHub repositories
 * via archive zipballs and deploying them with atomic updates, rollback protection,
 * and automatic subpath route adaptation.
 */
class GitHubDeployer
{
    private string $tmpPath;

    public function __construct(string $tmpPath)
    {
        $this->tmpPath = rtrim($tmpPath, '\\/');
        if (!is_dir($this->tmpPath)) {
            @mkdir($this->tmpPath, 0755, true);
        }
    }

    /**
     * Parse and normalize GitHub repository URL.
     *
     * Supports:
     * - https://github.com/owner/repo
     * - https://github.com/owner/repo.git
     * - https://github.com/owner/repo/tree/branch
     * - git@github.com:owner/repo.git
     */
    public static function parseUrl(string $url): ?array
    {
        $clean = trim($url);
        $pattern = '#^(?:https?://github\.com/|git@github\.com:)([^/]+)/([^/\.]+)(?:\.git)?(?:/tree/([^/]+))?#i';
        if (!preg_match($pattern, $clean, $m)) {
            return null;
        }

        $owner = trim($m[1]);
        $repo = trim($m[2]);
        $branch = !empty($m[3]) ? trim($m[3]) : null;

        if ($owner === '' || $repo === '') {
            return null;
        }

        return [
            'owner' => $owner,
            'repo' => $repo,
            'branch' => $branch,
            'canonical_url' => "https://github.com/{$owner}/{$repo}"
        ];
    }

    /**
     * Download GitHub repository archive as ZIP.
     */
    public function downloadArchive(string $owner, string $repo, ?string $branch = null, ?string $token = null): array
    {
        $branchesToTry = $branch ? [$branch] : ['main', 'master'];
        $destFile = $this->tmpPath . DIRECTORY_SEPARATOR . 'gh_' . uniqid('', true) . '.zip';

        $headers = [
            'User-Agent: cPanel-Deployer-App',
            'Accept: application/vnd.github+json'
        ];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . trim($token);
        }

        $lastError = 'خطا در برقراری ارتباط با مخزن گیت‌هاب.';
        $lastHttpCode = 0;

        foreach ($branchesToTry as $b) {
            $url = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$b}.zip";
            if (!empty($token)) {
                $url = "https://api.github.com/repos/{$owner}/{$repo}/zipball/{$b}";
            }

            $fp = @fopen($destFile, 'w+b');
            if (!$fp) {
                return ['success' => false, 'error' => 'خطا در ایجاد فایل موقت برای دریافت پروژه.'];
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $exec = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            @fclose($fp);

            if ($exec && $httpCode === 200 && file_exists($destFile) && filesize($destFile) > 100) {
                return [
                    'success' => true,
                    'zip_path' => $destFile,
                    'branch' => $b,
                    'size' => filesize($destFile)
                ];
            }

            $lastHttpCode = $httpCode;
            if ($curlErr !== '') {
                $lastError = 'خطای اتصال شبکه: ' . $curlErr;
            } elseif ($httpCode === 404) {
                $lastError = "مخزن یا شاخه '{$b}' در گیت‌هاب یافت نشد (404). از صحیح بودن آدرس و شاخه مطمئن شوید.";
            } elseif ($httpCode === 401 || $httpCode === 403) {
                $lastError = "خطای احراز هویت یا دسترسی گیت‌هاب (HTTP {$httpCode}). در صورت خصوصی بودن مخزن، توکن شخصی (Personal Access Token) وارد کنید.";
            } else {
                $lastError = "خطای گیت‌هاب با کد پاسخ {$httpCode}.";
            }

            if (file_exists($destFile)) {
                @unlink($destFile);
            }
        }

        return ['success' => false, 'error' => $lastError, 'http_code' => $lastHttpCode];
    }

    /**
     * Fetch from GitHub and deploy using existing Deployer pipeline.
     */
    public function deployFromGitHub(
        string $url,
        string $slug,
        ?string $branch = null,
        ?string $token = null,
        bool $isUpdate = false,
        bool $fixRoutes = false,
        Deployer $deployer
    ): array {
        $parsed = self::parseUrl($url);
        if ($parsed === null) {
            return ['success' => false, 'error' => 'لینک مخزن گیت‌هاب نامعتبر است. فرمت صحیح: https://github.com/owner/repo'];
        }

        $targetBranch = $branch ?: $parsed['branch'];
        $download = $this->downloadArchive($parsed['owner'], $parsed['repo'], $targetBranch, $token);

        if (!$download['success']) {
            return ['success' => false, 'error' => $download['error']];
        }

        $zipPath = $download['zip_path'];
        $fileInfo = [
            'name' => "{$parsed['repo']}-{$download['branch']}.zip",
            'type' => 'application/zip',
            'tmp_name' => $zipPath,
            'error' => UPLOAD_ERR_OK,
            'size' => $download['size'],
            'is_local' => true
        ];

        try {
            $result = $deployer->deploy($fileInfo, $slug, $isUpdate, $fixRoutes);
            @unlink($zipPath);

            if (!$result['success']) {
                return ['success' => false, 'error' => $result['message']];
            }

            return [
                'success' => true,
                'message' => $isUpdate
                    ? "سایت با موفقیت از شاخه '{$download['branch']}' گیت‌هاب به‌روزرسانی شد."
                    : "پروژه با موفقیت از شاخه '{$download['branch']}' گیت‌هاب دریافت و مستقر شد.",
                'stats' => $result['stats'] ?? [],
                'github' => [
                    'url' => $parsed['canonical_url'],
                    'owner' => $parsed['owner'],
                    'repo' => $parsed['repo'],
                    'branch' => $download['branch'],
                    'has_token' => !empty($token),
                    'token' => !empty($token) ? $token : null
                ]
            ];
        } catch (\Throwable $e) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return ['success' => false, 'error' => 'خطا در استخراج و دیپلوی پروژه: ' . $e->getMessage()];
        }
    }
}
