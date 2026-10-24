<?php
declare(strict_types=1);

require_once __DIR__ . '/../panel_core/Security.php';
require_once __DIR__ . '/../panel_core/SiteManager.php';
require_once __DIR__ . '/../panel_core/Deployer.php';

use Panel\Security;
use Panel\SiteManager;
use Panel\Deployer;

function assertConditionDeploy(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException("FAILED: $msg");
    }
    echo "  [PASS] $msg\n";
}

echo "=== Running Deployer & SiteManager Tests ===\n";

$tmpBase = __DIR__ . '/tmp_test_env_' . uniqid();
$fakePublicHtml = $tmpBase . '/public_html';
$fakeDataPath = $tmpBase . '/panel_data';

@mkdir($fakePublicHtml, 0755, true);
@mkdir($fakeDataPath, 0755, true);

$samplesDir = dirname(__DIR__) . '/samples';
$deployer = new Deployer($fakePublicHtml, $fakeDataPath);
$siteManager = new SiteManager($fakeDataPath, $fakePublicHtml);

try {
    // -------------------------------------------------------------
    // Test 1: Deploy Standard Static ZIP
    // -------------------------------------------------------------
    echo "\n--- Test 1: Standard Static Deployment ---\n";
    $staticZip = $samplesDir . '/sample-static.zip';
    $tempCopy1 = $fakeDataPath . '/temp1.zip';
    copy($staticZip, $tempCopy1);

    $fileInfo1 = [
        'name' => 'sample-static.zip',
        'type' => 'application/zip',
        'tmp_name' => $tempCopy1,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCopy1)
    ];

    $res1 = $deployer->deploy($fileInfo1, 'my-static');
    assertConditionDeploy($res1['success'], "Deploy static site should succeed: " . ($res1['message'] ?? ''));
    assertConditionDeploy(is_file($fakePublicHtml . '/my-static/index.html'), "index.html must exist directly in /my-static/");
    assertConditionDeploy(is_file($fakePublicHtml . '/my-static/about.html'), "about.html must exist in /my-static/");
    assertConditionDeploy(is_file($fakePublicHtml . '/my-static/css/style.css'), "style.css must exist in /my-static/css/");

    // Record in SiteManager
    $siteManager->save([
        'name' => 'My Static Website',
        'slug' => 'my-static',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $savedSite = $siteManager->get('my-static');
    assertConditionDeploy($savedSite !== null, "SiteManager must find saved site");
    assertConditionDeploy($savedSite['type'] === 'static', "Site type must be 'static'");
    assertConditionDeploy($savedSite['file_count'] >= 4, "File count must be at least 4");
    assertConditionDeploy($savedSite['status'] === 'active', "Status must be 'active'");

    // -------------------------------------------------------------
    // Test 2: Deploy Wrapped ZIP (Strip Single Root Folder)
    // -------------------------------------------------------------
    echo "\n--- Test 2: Stripping Wrapped Root Folder ---\n";
    $wrappedZip = $samplesDir . '/sample-static-wrapped.zip';
    $tempCopy2 = $fakeDataPath . '/temp2.zip';
    copy($wrappedZip, $tempCopy2);

    $fileInfo2 = [
        'name' => 'sample-static-wrapped.zip',
        'type' => 'application/zip',
        'tmp_name' => $tempCopy2,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCopy2)
    ];

    $res2 = $deployer->deploy($fileInfo2, 'wrapped-site');
    assertConditionDeploy($res2['success'], "Deploy wrapped site should succeed");
    // Verify that single root folder was unwrapped!
    assertConditionDeploy(is_file($fakePublicHtml . '/wrapped-site/index.html'), "Wrapped site's index.html must be directly at root of slug folder");
    assertConditionDeploy(!is_dir($fakePublicHtml . '/wrapped-site/my-static-project'), "Wrapped wrapper directory must have been stripped");

    // -------------------------------------------------------------
    // Test 3: Deploy PHP Site
    // -------------------------------------------------------------
    echo "\n--- Test 3: PHP Site Deployment & Type Detection ---\n";
    $phpZip = $samplesDir . '/sample-php.zip';
    $tempCopy3 = $fakeDataPath . '/temp3.zip';
    copy($phpZip, $tempCopy3);

    $fileInfo3 = [
        'name' => 'sample-php.zip',
        'type' => 'application/zip',
        'tmp_name' => $tempCopy3,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCopy3)
    ];

    $res3 = $deployer->deploy($fileInfo3, 'my-php');
    assertConditionDeploy($res3['success'], "Deploy PHP site should succeed");
    assertConditionDeploy(is_file($fakePublicHtml . '/my-php/index.php'), "index.php must exist in /my-php/");
    assertConditionDeploy(is_file($fakePublicHtml . '/my-php/.htaccess'), ".htaccess must exist in /my-php/");

    $siteManager->save([
        'name' => 'PHP Application',
        'slug' => 'my-php',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $savedPhpSite = $siteManager->get('my-php');
    assertConditionDeploy($savedPhpSite['type'] === 'php', "SiteManager must recognize site type as 'php'");
    assertConditionDeploy($savedPhpSite['has_php'] === true, "has_php must be true");

    // -------------------------------------------------------------
    // Test 4: Update Existing Site (Atomic Swap)
    // -------------------------------------------------------------
    echo "\n--- Test 4: Update Existing Site ---\n";
    // We update my-static with the PHP zip
    $tempCopy4 = $fakeDataPath . '/temp4.zip';
    copy($phpZip, $tempCopy4);

    $fileInfo4 = [
        'name' => 'updated.zip',
        'type' => 'application/zip',
        'tmp_name' => $tempCopy4,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCopy4)
    ];

    $res4 = $deployer->deploy($fileInfo4, 'my-static', true);
    assertConditionDeploy($res4['success'], "Update site should succeed: " . ($res4['message'] ?? ''));
    assertConditionDeploy(is_file($fakePublicHtml . '/my-static/index.php'), "Updated site now contains index.php");

    // -------------------------------------------------------------
    // Test 5: Malicious Zip Slip Attack Defense
    // -------------------------------------------------------------
    echo "\n--- Test 5: Zip Slip Defense ---\n";
    $evilZipPath = $fakeDataPath . '/evil_slip.zip';
    $evilZip = new ZipArchive();
    $evilZip->open($evilZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    // Add path traversal entry
    $evilZip->addFromString('../outside_evil.txt', 'MALICIOUS CONTENT');
    $evilZip->close();

    $fileInfoEvil = [
        'name' => 'evil_slip.zip',
        'type' => 'application/zip',
        'tmp_name' => $evilZipPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($evilZipPath)
    ];

    $resEvil = $deployer->deploy($fileInfoEvil, 'evil-target');
    assertConditionDeploy(!$resEvil['success'], "Zip Slip attack MUST be rejected by Deployer");
    assertConditionDeploy(str_contains($resEvil['message'], 'Zip Slip Detected'), "Error message should mention Zip Slip detection");
    assertConditionDeploy(!file_exists($fakePublicHtml . '/outside_evil.txt'), "Malicious file must not exist on disk");

    // -------------------------------------------------------------
    // Test 6: Decompression Bomb Protection
    // -------------------------------------------------------------
    echo "\n--- Test 6: Decompression Bomb Defense ---\n";
    // Deployer with very low max extract size (e.g. 100 bytes)
    $bombDeployer = new Deployer($fakePublicHtml, $fakeDataPath, 1048576, 50, 100);
    $tempCopyBomb = $fakeDataPath . '/temp_bomb.zip';
    copy($staticZip, $tempCopyBomb);

    $fileInfoBomb = [
        'name' => 'bomb.zip',
        'type' => 'application/zip',
        'tmp_name' => $tempCopyBomb,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCopyBomb)
    ];

    $resBomb = $bombDeployer->deploy($fileInfoBomb, 'bomb-site');
    assertConditionDeploy(!$resBomb['success'], "Exceeding uncompressed size must be rejected");
    assertConditionDeploy(str_contains($resBomb['message'], 'Zip Bomb Protection'), "Error message should mention Zip Bomb protection");

    // -------------------------------------------------------------
    // Test 7: Delete Deployed Site
    // -------------------------------------------------------------
    echo "\n--- Test 7: Site Deletion ---\n";
    $deleted = $deployer->removeSiteDirectory('my-static');
    assertConditionDeploy($deleted, "removeSiteDirectory must return true");
    assertConditionDeploy(!is_dir($fakePublicHtml . '/my-static'), "Site directory /my-static/ must be deleted from disk");

    $siteManager->delete('my-static');
    assertConditionDeploy($siteManager->get('my-static') === null, "Site must be removed from sites.json");

    echo "\n=== All Deployer & SiteManager Tests Passed ===\n\n";
} finally {
    // Cleanup temporary test environment
    Security::deleteDirectory($tmpBase);
}
