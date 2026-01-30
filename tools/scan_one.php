<?php
require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;
use Symfony\Component\Finder\Finder;

$generator = new Generator();
$finder = new Finder();
$finder->files()->in(__DIR__ . '/../app/Swagger')->name('OpenApi.php');

try {
    $analysis = $generator->generate($finder);
    echo "Annotations: " . count($analysis->annotations) . PHP_EOL;
    $pathItems = $analysis->getAnnotationsOfType('OpenApi\\Annotations\\PathItem');
    echo "PathItems found: " . count($pathItems) . PHP_EOL;
    foreach ($analysis->annotations as $a) {
        echo get_class($a) . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "Exception: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
