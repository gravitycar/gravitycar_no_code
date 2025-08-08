<?php
require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Factories\ModelFactory;

$gc = new Gravitycar();
$gc->bootstrap();

echo "Available models:\n";
$models = ModelFactory::getAvailableModels();
foreach ($models as $model) {
    echo "  - $model\n";
}
echo "Total: " . count($models) . "\n";
