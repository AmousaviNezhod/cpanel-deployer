<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/FileManager.php';

echo "=== Running File Manager & Deployer Storage Tests ===\n";

$tmpDir = __DIR__ . '/tmp_fm_test_' . uniqid();
$tmpPublic = $tmpDir . '/public_html';
$tmpData = $tmpDir . '/panel_data';
mkdir($tmpPublic, 0755, true);
mkdir($tmpData, 0755, true);

// Create sample site 'test-site'
$siteDir = $tmpPublic . '/test-site';
mkdir($siteDir . '/assets/css', 0755, true);
file_put_contents($siteDir . '/index.html', '<h1>Hello World</h1>');
file_put_contents($siteDir . '/assets/css/style.css', 'body { color: red; }');

$fm = new \Panel\FileManager($tmpPublic, $tmpData);

// 1. List root directory
$list = $fm->listDirectory('test-site', '');
assert($list['success'] === true, 'List root must succeed');
assert(count($list['folders']) === 1, 'Must have 1 folder (assets)');
assert($list['folders'][0]['name'] === 'assets', 'Folder must be assets');
assert(count($list['files']) === 1, 'Must have 1 file (index.html)');
assert($list['files'][0]['name'] === 'index.html', 'File must be index.html');
assert($list['files'][0]['is_text'] === true, 'index.html must be text editable');
echo "  [PASS] listDirectory in site root succeeded\n";

// 2. Traversal defense
$traversal = $fm->listDirectory('test-site', '../../');
assert($traversal['success'] === false, 'Directory traversal must be rejected');
echo "  [PASS] Directory traversal attempt blocked\n";

// 3. Read file
$read = $fm->readFile('test-site', 'index.html');
assert($read['success'] === true, 'Read index.html must succeed');
assert($read['content'] === '<h1>Hello World</h1>', 'Content must match');
assert($read['syntax'] === 'html', 'Syntax must be html');
echo "  [PASS] readFile returned exact content and syntax\n";

// 4. Save file
$save = $fm->saveFile('test-site', 'index.html', '<h1>Updated Content</h1>');
assert($save['success'] === true, 'Save index.html must succeed');
assert(file_get_contents($siteDir . '/index.html') === '<h1>Updated Content</h1>', 'File on disk must be updated');
echo "  [PASS] saveFile updated content successfully\n";

// 5. Create new file
$create = $fm->createFile('test-site', '', 'about.html');
assert($create['success'] === true, 'Create about.html must succeed');
assert(file_exists($siteDir . '/about.html'), 'Created file must exist on disk');
echo "  [PASS] createFile created file successfully\n";

// 6. Create directory
$mkdir = $fm->createDirectory('test-site', '', 'images');
assert($mkdir['success'] === true, 'Create directory images must succeed');
assert(is_dir($siteDir . '/images'), 'Created directory must exist on disk');
echo "  [PASS] createDirectory created directory successfully\n";

// 7. Rename item
$rename = $fm->renameItem('test-site', 'about.html', 'about-us.html');
assert($rename['success'] === true, 'Rename about.html must succeed');
assert(!file_exists($siteDir . '/about.html'), 'Old filename must not exist');
assert(file_exists($siteDir . '/about-us.html'), 'New filename must exist');
echo "  [PASS] renameItem renamed file successfully\n";

// 8. Delete item
$del = $fm->deleteItem('test-site', 'about-us.html');
assert($del['success'] === true, 'Delete about-us.html must succeed');
assert(!file_exists($siteDir . '/about-us.html'), 'Deleted file must not exist');
echo "  [PASS] deleteItem deleted file successfully\n";

// 9. Root deletion prevention
$delRoot = $fm->deleteItem('test-site', '');
assert($delRoot['success'] === false, 'Deleting site root via file manager must be forbidden');
echo "  [PASS] Site root deletion attempt blocked\n";

// 10. Deployer Storage Footprint Calculation
$sm = new \Panel\SiteManager($tmpData, $tmpPublic);
$storage = $sm->getDeployerStorageSize();
assert(isset($storage['bytes']) && $storage['bytes'] > 0, 'Deployer storage bytes must be positive');
assert(isset($storage['files']) && $storage['files'] > 0, 'Deployer file count must be positive');
echo "  [PASS] getDeployerStorageSize returned {$storage['formatted']} across {$storage['files']} files\n";

// Cleanup
\Panel\Security::deleteDirectory($tmpDir);
echo "=== All File Manager & Storage Tests Passed ===\n";
