<?php
declare(strict_types=1);

/**
 * Build Sample ZIP files for testing and packaging
 */

$rootDir = dirname(__DIR__);
$samplesDir = $rootDir . '/samples';

function createZipFromFolder(string $sourceDir, string $outZipPath, ?string $prefix = null): void
{
    $zip = new ZipArchive();
    if ($zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create ZIP: $outZipPath");
    }

    $sourceDir = realpath($sourceDir);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        $zipEntryPath = $prefix ? ($prefix . '/' . $relativePath) : $relativePath;

        if ($file->isDir()) {
            $zip->addEmptyDir($zipEntryPath);
        } else {
            $zip->addFile($filePath, $zipEntryPath);
        }
    }

    $zip->close();
    echo "Created: " . basename($outZipPath) . " (" . filesize($outZipPath) . " bytes)\n";
}

// 1. sample-static.zip (plain files in root)
createZipFromFolder($samplesDir . '/sample-static', $samplesDir . '/sample-static.zip');

// 2. sample-static-wrapped.zip (all files inside single folder 'my-static-project')
createZipFromFolder($samplesDir . '/sample-static', $samplesDir . '/sample-static-wrapped.zip', 'my-static-project');

// 3. sample-php.zip (plain PHP site)
createZipFromFolder($samplesDir . '/sample-php', $samplesDir . '/sample-php.zip');

echo "All sample archives generated successfully.\n";
