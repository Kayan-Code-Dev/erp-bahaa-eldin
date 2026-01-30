<?php
require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;
use Symfony\Component\Finder\Finder;

$paths = [__DIR__ . '/../app', __DIR__ . '/../routes'];

$finder = new Finder();
$finder->files()->in($paths)->name('*.php')->sortByName();

$generator = new Generator();

try {
    $generator->withContext(function (Generator $g, $analysis, $context) use ($finder) {
        // perform generation into the provided Analysis object
        $g->generate($finder, $analysis, false); // disable validation to allow partial inspection

        echo "Scanned annotations summary:\n";
        $infos = $analysis->getAnnotationsOfType('\\OpenApi\\Annotations\\Info');
        $pathItems = $analysis->getAnnotationsOfType('\\OpenApi\\Annotations\\PathItem');
        $openApis = $analysis->getAnnotationsOfType('\\OpenApi\\Annotations\\OpenApi');

        echo "  Info found: " . count($infos) . PHP_EOL;
        echo "  PathItem found: " . count($pathItems) . PHP_EOL;
        echo "  OpenApi root found: " . count($openApis) . PHP_EOL;

        foreach ($pathItems as $p) {
            echo "  PathItem: " . ($p->path ?? '(no path)') . PHP_EOL;
        }
        // inspect openapi object
        if ($analysis->openapi) {
            echo "\nOpenApi object details:\n";
            $oa = $analysis->openapi;
            echo "  openapi version: " . ($oa->openapi ?? '(none)') . PHP_EOL;
            echo "  info present: " . (isset($oa->info) ? 'yes' : 'no') . PHP_EOL;
            echo "  paths present: " . (isset($oa->paths) ? 'yes' : 'no') . PHP_EOL;
            // print info structure if present
            if (isset($oa->info)) {
                var_export($oa->info);
                echo PHP_EOL;
            }
        }
        echo "\nAll parsed annotation classes:\n";
        foreach ($analysis->annotations as $ann) {
            echo "  - " . get_class($ann) . PHP_EOL;
        }
    });

} catch (Throwable $e) {
    echo "Exception: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
