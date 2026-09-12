<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Hostinger keeps production secrets outside the Git checkout in laravel_app.
// Reuse that environment file without copying it into the public web tree.
$sharedEnvironmentPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'laravel_app';
if (is_file($sharedEnvironmentPath.DIRECTORY_SEPARATOR.'.env')) {
    $app->useEnvironmentPath($sharedEnvironmentPath);
}

$app->handleRequest(Request::capture());
