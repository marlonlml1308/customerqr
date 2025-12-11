<?php

use App\Filament\Pages\RegistrarCliente;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProxyController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/clientes/create');
});

// Rutas del formulario de clientes
Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');

// Rutas del proxy a la API externa (con nombres)
Route::get('/proxy/get-customer', [ProxyController::class, 'getCustomerByDocument'])
    ->name('proxy.getCustomer');
Route::post('/proxy/create-customer', [ProxyController::class, 'createCustomer'])
    ->name('proxy.createCustomer');