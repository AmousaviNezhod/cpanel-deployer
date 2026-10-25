<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/DatabaseManager.php';

echo "=== Running Database & Disk Stats Tests ===\n";

$tmpDir = __DIR__ . '/tmp_db_test_' . uniqid();
$tmpData = $tmpDir . '/data';
$tmpHtml = $tmpDir . '/public_html';
mkdir($tmpData, 0755, true);
mkdir($tmpHtml, 0755, true);

// 1. Test Disk Stats
$siteManager = new \Panel\SiteManager($tmpData, $tmpHtml);
$stats = $siteManager->getDiskStats();
assert(isset($stats['host']['total_formatted']), 'Host total formatted must exist');
assert(isset($stats['host']['free_formatted']), 'Host free formatted must exist');
assert(isset($stats['services']['total_bytes']), 'Services total bytes must exist');
echo "  [PASS] Disk stats structure is valid\n";

// 2. Test Database Manager: SQLite creation
$dbManager = new \Panel\DatabaseManager($tmpData, $tmpHtml);
$createRes = $dbManager->createSqliteDb('test_app');
assert($createRes['success'] === true, 'SQLite DB creation must succeed');
echo "  [PASS] SQLite database creation succeeded\n";

// 3. Test Database listing
$allDbs = $dbManager->listAll();
assert(count($allDbs['sqlite']) >= 1, 'SQLite database must be listed');
$firstDb = $allDbs['sqlite'][0];
assert($firstDb['filename'] === 'test_app.sqlite', 'Created database name must match');
echo "  [PASS] SQLite database recognized in list\n";

// 4. Test Table creation & Query Execution
$createTableQuery = "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT);";
$qRes1 = $dbManager->executeQuery($firstDb['id'], $createTableQuery);
assert($qRes1['success'] === true, 'CREATE TABLE must succeed');
echo "  [PASS] Table creation via executeQuery succeeded\n";

$insertQuery = "INSERT INTO users (name, email) VALUES ('Amir', 'amir@example.com');";
$qRes2 = $dbManager->executeQuery($firstDb['id'], $insertQuery);
assert($qRes2['success'] === true, 'INSERT must succeed');
assert($qRes2['affected_rows'] === 1, 'Affected rows must be 1');
echo "  [PASS] INSERT query succeeded\n";

// 5. Test Table Inspector
$tablesRes = $dbManager->getTables($firstDb['id']);
assert($tablesRes['success'] === true, 'getTables must succeed');
$foundUsers = false;
foreach ($tablesRes['tables'] as $t) {
    if ($t['name'] === 'users') {
        $foundUsers = true;
        assert($t['rows'] === 1, 'Row count of users must be 1');
    }
}
assert($foundUsers, 'users table must be present');
echo "  [PASS] getTables returned tables with exact row count\n";

// 6. Test SELECT Query Execution
$selectQuery = "SELECT id, name, email FROM users;";
$qRes3 = $dbManager->executeQuery($firstDb['id'], $selectQuery);
assert($qRes3['success'] === true, 'SELECT must succeed');
assert($qRes3['is_select'] === true, 'is_select flag must be true');
assert(count($qRes3['rows']) === 1, 'Must return 1 row');
assert($qRes3['rows'][0]['name'] === 'Amir', 'Returned data must match');
echo "  [PASS] SELECT query returned expected columns and records\n";

// 7. Test SQLite Deletion
$delRes = $dbManager->deleteSqliteDb($firstDb['id']);
assert($delRes['success'] === true, 'deleteSqliteDb must succeed');
echo "  [PASS] SQLite database deleted successfully\n";

// Cleanup
\Panel\Security::deleteDirectory($tmpDir);
echo "=== All Database & Disk Tests Passed ===\n";
