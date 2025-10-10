<?php

$dirs = array(__DIR__);
$pending = array();

while (count($dirs)) {
    $dir = $dirs[0];

    $nodes = array_diff(scandir($dir), array('.', '..'));
    foreach ($nodes as $node) {
        if (str_starts_with($node, '.')) {
            continue;
        }

        $path = $dir . '/' . $node;
        if (is_dir($path)) {
            $dirs[] = $path;
        } elseif (preg_match('/\.php$/', $path)) {
            try {
                include_once $path;
            } catch (Error) {
                $pending[] = $path;
            }
        }
    }

    array_shift($dirs);
}

foreach ($pendings as $pending) {
    include_once $pending;
}
