<?php

use Illuminate\Support\Facades\Route;

// Catch-all route to serve the React SPA for all frontend paths
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|up).*$');

Route::get('/', function () {
    return view('app');
});
