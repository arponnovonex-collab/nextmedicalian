<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Ensure production .env exists on server
if (! file_exists(__DIR__ . '/../.env')) {
    @file_put_contents(__DIR__ . '/../.env', "APP_NAME=\"NEXT MEDICALIAN\"\nAPP_ENV=production\nAPP_KEY=base64:nZX/PyBk6A1Gx5Vh7hOlP2KdUE4oxZrkxdI7P4ZTeXA=\nAPP_DEBUG=false\nAPP_TIMEZONE=Asia/Dhaka\nAPP_URL=https://next.thuhashop.com\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=u825551938_nextmed\nDB_USERNAME=u825551938_nextmeduser\nDB_PASSWORD=NextMed2026SecurePass\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\nFILESYSTEM_DISK=local\n");
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
