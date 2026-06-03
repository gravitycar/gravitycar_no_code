<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure the test cache directory exists.
$testCacheDir = '/tmp/gravitycar_test_cache/';
if (!is_dir($testCacheDir)) {
    mkdir($testCacheDir, 0777, true);
}

// Seed the test metadata cache from the production file so ContainerConfig can
// build the DI container. Without this, any test that calls ServiceLocator::reset()
// triggers a container rebuild that fails because some earlier test may have cleared
// the production cache/metadata_cache.php.
$testCacheFile      = $testCacheDir . 'metadata_cache.php';
$productionCacheFile = __DIR__ . '/../cache/metadata_cache.php';

if (!file_exists($testCacheFile) && file_exists($productionCacheFile)) {
    copy($productionCacheFile, $testCacheFile);
}
