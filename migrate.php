<?php

/**
 * Convenience runner for Phinx migrations inside this package.
 *
 * Usage:
 *   php migrate.php
 *   php migrate.php --debug=true
 *   php migrate.php rollback
 */

$root = __DIR__;

if (!file_exists($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` first.\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$args        = $_SERVER['argv'] ?? [];
$script      = array_shift($args);
$debug       = false;
$commandArgs = [];

foreach ($args as $arg) {
    if (preg_match('/^--debug=(true|false)$/', $arg, $m)) {
        $debug = $m[1] === 'true';
        continue;
    }
    $commandArgs[] = $arg;
}

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

$command = $commandArgs[0] ?? 'migrate';
$extra   = array_slice($commandArgs, 1);

$app = new PhinxApplication();
$app->setAutoExit(false);

$input = new ArrayInput(array_merge(['command' => $command], $extra));
$input->setInteractive(false);

$exitCode = $app->run($input, new ConsoleOutput());
exit($exitCode);