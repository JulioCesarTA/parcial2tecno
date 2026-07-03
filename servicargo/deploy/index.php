<?php

// Front controller para deploy en subcarpeta (proyecto2/), sin SSH.
// Reemplaza a public/index.php: el resto de Laravel vive en ./servicargo/
// (hermano de este archivo, en vez de un nivel arriba como en el layout normal).
// Nombre de carpeta elegido a propósito: es el mismo que deja "git clone" del repo,
// así no hace falta renombrarla a mano en el servidor en cada deploy.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/servicargo/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/servicargo/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/servicargo/bootstrap/app.php';

// El "public path" real es esta carpeta (donde vive index.php), no servicargo/public
// (esa carpeta no existe en este layout: build/, favicon, etc. están acá mismo).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
