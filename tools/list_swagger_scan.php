<?php
require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$paths = [__DIR__ . '/../app', __DIR__ . '/../routes'];

$finder = new Finder();
$finder->files()->in($paths)->name('*.php')->sortByName();

foreach ($finder as $file) {
    echo $file->getRealPath() . PHP_EOL;
}
