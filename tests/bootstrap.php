<?php
declare(strict_types=1);

// Composer autoloader: tests/vendor (recommended) or vendor in the module folder
$autoloaders = [__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'];
foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}

require __DIR__ . '/stubs/ProcessWireStubs.php';
require __DIR__ . '/../SnowFallAnimation.module';
require __DIR__ . '/Unit/ModuleTestCase.php';
