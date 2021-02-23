#!/usr/bin/env php
<?php
/**
 * This file is called from composer.json and .travis.yml to install phpdoc in the bin directory.
 * @file
 */

$phpdocBin = dirname(__DIR__).'/bin/phpdoc';
if (!file_exists($phpdocBin)) {
    echo "Downloading phpdoc to bin/phpdoc\n";
    $release = 'v3.0.0';
    $releaseUrl = "https://github.com/phpDocumentor/phpDocumentor2/releases/download/$release/phpDocumentor.phar";
    copy($releaseUrl, $phpdocBin);
    copy($releaseUrl.'.asc', $phpdocBin.'.asc');
}
if (!is_executable($phpdocBin)) {
    chmod($phpdocBin, 0700);
}
