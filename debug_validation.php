<?php
require 'vendor/autoload.php';

use Gravitycar\Validation\AlphanumericValidation;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

$logger = new Logger('test');
$logger->pushHandler(new NullHandler());
$validator = new AlphanumericValidation($logger);

echo "Testing specific cases that are failing:\n";

// Test spaces only
echo "Spaces only '   ': " . ($validator->validate('   ') ? 'true' : 'false') . "\n";
echo "Single space ' ': " . ($validator->validate(' ') ? 'true' : 'false') . "\n";

// Test boolean false
echo "Boolean false: " . ($validator->validate(false) ? 'true' : 'false') . "\n";

// Test what ctype_alnum returns for empty string
echo "ctype_alnum(''): " . (ctype_alnum('') ? 'true' : 'false') . "\n";

// Test what (string)false returns
$falseAsString = (string)false;
echo "String conversion of false: '" . $falseAsString . "' (length: " . strlen($falseAsString) . ")\n";
echo "ctype_alnum((string)false): " . (ctype_alnum($falseAsString) ? 'true' : 'false') . "\n";

// Test shouldValidateValue behavior with false
$reflection = new ReflectionClass($validator);
$method = $reflection->getMethod('shouldValidateValue');
$method->setAccessible(true);

echo "shouldValidateValue(false): " . ($method->invoke($validator, false) ? 'true' : 'false') . "\n";
echo "shouldValidateValue('   '): " . ($method->invoke($validator, '   ') ? 'true' : 'false') . "\n";
