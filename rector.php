<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/network-media-library.php',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        codeQuality: true,
        deadCode: true,
        earlyReturn: true,
        typeDeclarations: true,
    )
    // WordPress remove_filter/add_filter requires [$this, 'method'] array syntax for removability.
    ->withSkip([
        ArrayToFirstClassCallableRector::class => [
            __DIR__ . '/src/MediaSwitcher.php',
        ],
    ])
;
