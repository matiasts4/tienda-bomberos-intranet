<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ruta para obtener CSRF token (necesaria para SPA)
Route::middleware('web')->group(function () {
    // Esta ruta es proporcionada automáticamente por Sanctum
    // Pero la incluimos aquí para claridad
});
