<?php
require __DIR__ . '/../vendor/autoload.php';

// call the scan function in the OpenApi namespace
$analysis = \OpenApi\scan([__DIR__ . '/../app']);

echo "Annotations found: " . count($analysis->annotations) . PHP_EOL;
// try to count PathItem annotations
$pathItems = array_filter($analysis->annotations, function($a){ return $a instanceof OpenApi\Annotations\PathItem; });
echo "PathItems: " . count($pathItems) . PHP_EOL;

foreach ($analysis->annotations as $ann) {
    echo get_class($ann) . "\n";
}

// dump names
$paths = $analysis->getAnnotationsOfType('OpenApi\\Annotations\\PathItem');
if ($paths) {
    foreach ($paths as $p) {
        echo "Found path: " . ($p->path ?? '(no path)') . PHP_EOL;
    }
}
