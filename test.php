<?php
include 'vendor/autoload.php';
use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;

$gc = new Gravitycar();
$gc->bootstrap();
$model = ModelFactory::new('Movie_quotes');
print("done\n");
