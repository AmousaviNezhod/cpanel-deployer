<?php
declare(strict_types=1);

namespace Panel;

final class DatabaseManager
{
    private string $dataPath;
    private string $publicHtmlPath;
    private string $sqliteDir;
    private string $mysqlConfigFile;

    public function __construct(string $dataPath, string $publicHtmlPath)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->publicHtmlPath = rtrim($publicHtmlPath, '/\\');
        $this->sqliteDir = $this->dataPath . DIRECTORY_SEPARATOR . 'databases';
        $this->mysqlConfigFile = $this->dataPath . DIRECTORY_SEPARATOR . 'mysql_connections.json';

        if (!is_dir($this->sqliteDir)) {
            @mkdir($this->sqliteDir, 0755, true);
        }
    }

    /**
     * List all available SQLite and MySQL databases.
     */
    public function listAll(): array
    {
        return [
            'sqlite' => $this->listSqliteDatabases(),
            'mysql' => $this->listMysqlConnections()
        ];
    }

    /**
     * List SQLite databases in panel_data/databases and inside deployed sites.
     */
    public function listSqliteDatabases(): array
    {
        $databases = [];

        // 1. Central panel databases
        if (is_dir($this->sqliteDir)) {
            $files = @scandir($this->sqliteDir) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['sqlite', 'sqlite3', 'db'], true)) {
                    $fullPath = $this->sqliteDir . DIRECTORY_SEPARATOR . $file;
                    $size = @filesize($fullPath) ?: 0;
                    $databases[] = [
                        'id' => 'sqlite:' . $file,
                        'name' => $file,
                        'type' => 'sqlite',
                        'scope' => 'سیستمی (panel_data)',
                        'filename' => $file,
                        'path' => $fullPath,
                        'size' => $size,
                        'formatted_size' => SiteManager::formatBytes($size),
                        'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($fullPath))
                    ];
                }
            }
        }

        // 2. Discover SQLite files inside deployed sites
        if (is_dir($this->publicHtmlPath)) {
            $siteDirs = @scandir($this->publicHtmlPath) ?: [];
            foreach ($siteDirs as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir($this->publicHtmlPath . DIRECTORY_SEPARATOR . $dir)) {
                    continue;
                }
                $siteDir = $this->publicHtmlPath . DIRECTORY_SEPARATOR . $dir;
                $siteFiles = @scandir($siteDir) ?: [];
                foreach ($siteFiles as $sf) {
                    $ext = strtolower(pathinfo($sf, PATHINFO_EXTENSION));
                    if (in_array($ext, ['sqlite', 'sqlite3', 'db'], true)) {
                        $fullPath = $siteDir . DIRECTORY_SEPARATOR . $sf;
                        $size = @filesize($fullPath) ?: 0;
                        $databases[] = [
                            'id' => 'sqlite:' . $dir . '/' . $sf,
                            'name' => $dir . ' / ' . $sf,
                            'type' => 'sqlite',
                            'scope' => "سایت ({$dir})",
                            'filename' => $sf,
                            'path' => $fullPath,
                            'size' => $size,
                            'formatted_size' => SiteManager::formatBytes($size),
                            'modified_at' => date('Y-m-d H:i:s', (int)@filemtime($fullPath))
                        ];
                    }
                }
            }
        }

        return $databases;
    }

    /**
     * Create a new SQLite database.
     */
    public function createSqliteDb(string $rawName): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($rawName));
        if ($name === '') {
            return ['success' => false, 'error' => 'نام دیتابیس نامعتبر است. فقط حروف انگلیسی، اعداد، _ و - مجاز است.'];
        }

        $filename = $name . '.sqlite';
        $fullPath = $this->sqliteDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($fullPath)) {
            return ['success' => false, 'error' => 'دیتابیسی با این نام در حال حاضر وجود دارد.'];
        }

        try {
            $pdo = new \PDO('sqlite:' . $fullPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE IF NOT EXISTS _deployer_meta (created_at TEXT);');
            $pdo->exec("INSERT INTO _deployer_meta VALUES ('" . date('c') . "');");
            $pdo = null;
            @chmod($fullPath, 0644);

            return ['success' => true, 'message' => "دیتابیس SQLite '{$filename}' با موفقیت ایجاد شد."];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'خطا در ایجاد دیتابیس: ' . $e->getMessage()];
        }
    }

    /**
     * Delete an SQLite database file safely.
     */
    public function deleteSqliteDb(string $filenameOrPath): array
    {
        $realPath = null;
        $all = $this->listSqliteDatabases();
        foreach ($all as $db) {
            if ($db['id'] === $filenameOrPath || $db['path'] === $filenameOrPath || $db['filename'] === $filenameOrPath) {
                $realPath = $db['path'];
                break;
            }
        }

        if ($realPath === null || !file_exists($realPath)) {
            return ['success' => false, 'error' => 'دیتابیس مورد نظر یافت نشد.'];
        }

        // Safety: verify path is inside dataPath/databases or publicHtmlPath
        if (!Security::isPathWithin($realPath, $this->sqliteDir) && !Security::isPathWithin($realPath, $this->publicHtmlPath)) {
            return ['success' => false, 'error' => 'مسیر فایل خارج از محدوده مجاز است.'];
        }

        if (@unlink($realPath)) {
            return ['success' => true, 'message' => 'دیتابیس با موفقیت حذف شد.'];
        }

        return ['success' => false, 'error' => 'خطا در حذف فایل دیتابیس از دیسک.'];
    }

    /**
     * List saved MySQL connections.
     */
    public function listMysqlConnections(): array
    {
        if (!file_exists($this->mysqlConfigFile)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->mysqlConfigFile), true);
        if (!is_array($data)) {
            return [];
        }

        $list = [];
        foreach ($data as $id => $item) {
            $list[] = [
                'id' => 'mysql:' . $id,
                'type' => 'mysql',
                'name' => $item['name'] ?? 'MySQL DB',
                'host' => $item['host'] ?? 'localhost',
                'port' => (int)($item['port'] ?? 3306),
                'database' => $item['database'] ?? '',
                'username' => $item['username'] ?? '',
                'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s')
            ];
        }
        return $list;
    }

    /**
     * Save / Test MySQL connection profile.
     */
    public function saveMysqlConnection(array $params): array
    {
        $name = Security::sanitizeText((string)($params['name'] ?? 'MySQL'));
        $host = trim((string)($params['host'] ?? 'localhost'));
        $port = (int)($params['port'] ?? 3306) ?: 3306;
        $database = trim((string)($params['database'] ?? ''));
        $username = trim((string)($params['username'] ?? ''));
        $password = (string)($params['password'] ?? '');

        if ($database === '' || $username === '') {
            return ['success' => false, 'error' => 'نام پایگاه داده و نام کاربری الزامی است.'];
        }

        // Test connection
        $testResult = $this->testMysql($host, $port, $database, $username, $password);
        if (!$testResult['success']) {
            return ['success' => false, 'error' => 'خطای اتصال به دیتابیس MySQL: ' . $testResult['error']];
        }

        // Save
        $connections = [];
        if (file_exists($this->mysqlConfigFile)) {
            $connections = json_decode((string)@file_get_contents($this->mysqlConfigFile), true) ?: [];
        }

        $id = uniqid('conn_', true);
        $connections[$id] = [
            'id' => $id,
            'name' => $name ?: $database,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password_encoded' => base64_encode($password),
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->mysqlConfigFile, json_encode($connections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($this->mysqlConfigFile, 0644);

        return ['success' => true, 'message' => "اتصال به پایگاه داده '{$database}' با موفقیت برقرار و ذخیره شد."];
    }

    /**
     * Delete MySQL connection profile.
     */
    public function deleteMysqlConnection(string $id): array
    {
        $id = str_replace('mysql:', '', $id);
        if (!file_exists($this->mysqlConfigFile)) {
            return ['success' => false, 'error' => 'پیکربندی یافت نشد.'];
        }
        $connections = json_decode((string)@file_get_contents($this->mysqlConfigFile), true) ?: [];
        if (!isset($connections[$id])) {
            return ['success' => false, 'error' => 'اتصال مورد نظر یافت نشد.'];
        }

        unset($connections[$id]);
        file_put_contents($this->mysqlConfigFile, json_encode($connections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return ['success' => true, 'message' => 'پروفایل اتصال MySQL حذف شد.'];
    }

    /**
     * Get tables and row count for selected database.
     */
    public function getTables(string $targetId): array
    {
        try {
            $pdo = $this->getPdoForTarget($targetId);
            $tables = [];

            if (str_starts_with($targetId, 'sqlite:')) {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC;");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tableName = $row['name'];
                    $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM \"{$tableName}\";");
                    $count = (int)($countStmt->fetchColumn() ?: 0);
                    $tables[] = [
                        'name' => $tableName,
                        'rows' => $count
                    ];
                }
            } else {
                $stmt = $pdo->query("SHOW TABLES;");
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $tableName = $row[0];
                    $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM `{$tableName}`;");
                    $count = (int)($countStmt->fetchColumn() ?: 0);
                    $tables[] = [
                        'name' => $tableName,
                        'rows' => $count
                    ];
                }
            }

            return ['success' => true, 'tables' => $tables];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'خطا در دریافت جداول: ' . $e->getMessage()];
        }
    }

    /**
     * Run SQL query on selected database.
     */
    public function executeQuery(string $targetId, string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['success' => false, 'error' => 'کوئری خالی است.'];
        }

        try {
            $pdo = $this->getPdoForTarget($targetId);
            $start = microtime(true);

            $trimmedUpper = strtoupper(ltrim($sql));
            $isSelect = str_starts_with($trimmedUpper, 'SELECT') ||
                        str_starts_with($trimmedUpper, 'SHOW') ||
                        str_starts_with($trimmedUpper, 'DESCRIBE') ||
                        str_starts_with($trimmedUpper, 'PRAGMA') ||
                        str_starts_with($trimmedUpper, 'EXPLAIN');

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $durationMs = round((microtime(true) - $start) * 1000, 2);

            if ($isSelect) {
                $rows = [];
                $columns = [];
                $count = 0;
                while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $count < 100) {
                    if (empty($columns)) {
                        $columns = array_keys($row);
                    }
                    $rows[] = $row;
                    $count++;
                }

                return [
                    'success' => true,
                    'is_select' => true,
                    'columns' => $columns,
                    'rows' => $rows,
                    'total_rows' => count($rows),
                    'duration_ms' => $durationMs,
                    'message' => "کوئری در {$durationMs} میلی‌ثانیه اجرا شد (" . count($rows) . " ردیف بازگردانده شد)."
                ];
            }

            $affected = $stmt->rowCount();
            return [
                'success' => true,
                'is_select' => false,
                'affected_rows' => $affected,
                'duration_ms' => $durationMs,
                'message' => "عملیات با موفقیت در {$durationMs} میلی‌ثانیه اجرا شد ({$affected} ردیف متأثر گردید)."
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'خطای اجرای SQL: ' . $e->getMessage()];
        }
    }

    /**
     * Establish PDO connection for target identifier.
     */
    private function getPdoForTarget(string $targetId): \PDO
    {
        if (str_starts_with($targetId, 'sqlite:')) {
            $all = $this->listSqliteDatabases();
            $targetPath = null;
            foreach ($all as $db) {
                if ($db['id'] === $targetId || $db['filename'] === $targetId) {
                    $targetPath = $db['path'];
                    break;
                }
            }

            if ($targetPath === null || !file_exists($targetPath)) {
                throw new \RuntimeException("دیتابیس SQLite مورد نظر یافت نشد.");
            }

            $pdo = new \PDO('sqlite:' . $targetPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }

        // MySQL
        $id = str_replace('mysql:', '', $targetId);
        if (!file_exists($this->mysqlConfigFile)) {
            throw new \RuntimeException("تنظیمات دیتابیس MySQL یافت نشد.");
        }
        $connections = json_decode((string)file_get_contents($this->mysqlConfigFile), true) ?: [];
        if (!isset($connections[$id])) {
            throw new \RuntimeException("پروفایل اتصال MySQL پیدا نشد.");
        }

        $conf = $connections[$id];
        $dsn = "mysql:host={$conf['host']};port={$conf['port']};dbname={$conf['database']};charset=utf8mb4";
        $password = base64_decode($conf['password_encoded'] ?? '');

        $pdo = new \PDO($dsn, $conf['username'], $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5
        ]);

        return $pdo;
    }

    /**
     * Test a MySQL connection directly.
     */
    private function testMysql(string $host, int $port, string $database, string $username, string $password): array
    {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5
            ]);
            $pdo->query("SELECT 1;");
            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
