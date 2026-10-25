<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/Deployer.php';
require_once __DIR__ . '/../panel_core/GitHubDeployer.php';

echo "=== Running GitHub Deployer Tests ===\n";

// 1. URL Parsing Tests
$p1 = \Panel\GitHubDeployer::parseUrl('https://github.com/AmousaviNezhod/cpanel-deployer');
assert($p1 !== null, 'p1 must parse');
assert($p1['owner'] === 'AmousaviNezhod', 'Owner must match');
assert($p1['repo'] === 'cpanel-deployer', 'Repo must match');
assert($p1['branch'] === null, 'Branch should be null');
assert($p1['canonical_url'] === 'https://github.com/AmousaviNezhod/cpanel-deployer', 'Canonical URL must match');

$p2 = \Panel\GitHubDeployer::parseUrl('https://github.com/torvalds/linux.git');
assert($p2 !== null, 'p2 must parse .git suffix');
assert($p2['repo'] === 'linux', 'Repo must not contain .git');

$p3 = \Panel\GitHubDeployer::parseUrl('https://github.com/vuejs/core/tree/v3.4.0');
assert($p3 !== null, 'p3 must parse branch/tag');
assert($p3['branch'] === 'v3.4.0', 'Branch must be v3.4.0');

$p4 = \Panel\GitHubDeployer::parseUrl('git@github.com:facebook/react.git');
assert($p4 !== null, 'p4 must parse git@ format');
assert($p4['owner'] === 'facebook' && $p4['repo'] === 'react', 'Owner and repo must match');

$pInvalid = \Panel\GitHubDeployer::parseUrl('https://gitlab.com/owner/repo');
assert($pInvalid === null, 'Non-github URL must be rejected');

echo "  [PASS] GitHub URL parser handles standard, .git, branch, and SSH formats\n";

// 2. Real Download & Deploy Test
$tmpDir = __DIR__ . '/tmp_gh_test_' . uniqid();
$tmpPublic = $tmpDir . '/public_html';
$tmpData = $tmpDir . '/panel_data';
$tmpTmp = $tmpData . '/tmp';
mkdir($tmpPublic, 0755, true);
mkdir($tmpData, 0755, true);
mkdir($tmpTmp, 0755, true);

$deployer = new \Panel\Deployer($tmpPublic, $tmpData);
$ghDeployer = new \Panel\GitHubDeployer($tmpTmp);

echo "  [INFO] Downloading test repository from GitHub (AmousaviNezhod/cpanel-deployer)...\n";
$deployRes = $ghDeployer->deployFromGitHub(
    'https://github.com/AmousaviNezhod/cpanel-deployer',
    'gh-test',
    branch: 'main',
    token: null,
    isUpdate: false,
    fixRoutes: true,
    deployer: $deployer
);

assert($deployRes['success'] === true, 'GitHub deployment must succeed: ' . ($deployRes['error'] ?? ''));
assert(file_exists($tmpPublic . '/gh-test/index.php'), 'index.php must exist directly in site root');
assert(isset($deployRes['github']['owner']) && $deployRes['github']['owner'] === 'AmousaviNezhod', 'Owner in metadata must match');
assert($deployRes['github']['branch'] === 'main', 'Branch in metadata must be main');
echo "  [PASS] deployFromGitHub successfully fetched, extracted, and deployed repository\n";

// Cleanup
\Panel\Security::deleteDirectory($tmpDir);
echo "=== All GitHub Deployer Tests Passed ===\n";
