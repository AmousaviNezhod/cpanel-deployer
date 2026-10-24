<?php
declare(strict_types=1);

/**
 * Package release ZIP for cPanel upload
 */

$rootDir = dirname(__DIR__);
$releaseZipPath = $rootDir . '/cpanel-deployer.zip';

$zip = new ZipArchive();
if ($zip->open($releaseZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException("Cannot create release ZIP");
}

$filesToInclude = [
    'install.php',
    'index.php',
    'README.md',
    '.htaccess',
    'panel_data/.htaccess',
    'panel_data/index.php',
];

foreach ($filesToInclude as $relPath) {
    $fullPath = $rootDir . '/' . $relPath;
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, $relPath);
    }
}

// Add panel_core recursively
$coreDir = realpath($rootDir . '/panel_core');
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($coreDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $fullPath = $item->getRealPath();
    $relPath = 'panel_core/' . str_replace('\\', '/', substr($fullPath, strlen($coreDir) + 1));
    if ($item->isDir()) {
        $zip->addEmptyDir($relPath);
    } else {
        $zip->addFile($fullPath, $relPath);
    }
}

// Add samples
if (file_exists($rootDir . '/samples/sample-static.zip')) {
    $zip->addFile($rootDir . '/samples/sample-static.zip', 'samples/sample-static.zip');
}
if (file_exists($rootDir . '/samples/sample-php.zip')) {
    $zip->addFile($rootDir . '/samples/sample-php.zip', 'samples/sample-php.zip');
}

$zip->close();

echo "Release package created: " . basename($releaseZipPath) . " (" . round(filesize($releaseZipPath) / 1024, 1) . " KB)\n";
