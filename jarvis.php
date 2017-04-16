#!/usr/bin/env php
<?php

/**
 * JARVIS home automation central core.
 *
 * @license MIT
 * @copyright 2017 Tim Gunter
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 * @version 1.0
 */

namespace Kaecyra\Jarvis\Core;

use \Garden\Daemon\Daemon;

use \Psr\Log\LoggerInterface;
use \Psr\Log\LogLevel;

// Switch to queue root
chdir(dirname($argv[0]));

// Include the core autoloader.

$paths = [
    __DIR__.'/vendor/autoload.php',     // locally
    __DIR__.'/../vendor/autoload.php',  // locally
    __DIR__.'/../../../autoload.php'    // dependency
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Run bootstrap
$container = new \Garden\Container\Container;
Core::bootstrap($container);

$exitCode = 0;
try {

    $daemon = $container->get(Daemon::class);
    $exitCode = $daemon->attach($argv);

} catch (\Garden\Daemon\Exception $ex) {

    $exceptionCode = $ex->getCode();
    if ($exceptionCode != 200) {

        if ($ex->getFile()) {
            $line = $ex->getLine();
            $file = $ex->getFile();
            $container->get(LoggerInterface::class)->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
        }
        $container->get(LoggerInterface::class)->log(LogLevel::ERROR, $ex->getMessage());
    }

} catch (\Exception $ex) {
    $exitCode = 1;

    if ($ex->getFile()) {
        $line = $ex->getLine();
        $file = $ex->getFile();
        $container->get(LoggerInterface::class)->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
    }
    $container->get(LoggerInterface::class)->log(LogLevel::ERROR, $ex->getMessage());
}

exit($exitCode);
