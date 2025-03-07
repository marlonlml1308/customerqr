<?php

use App\Filament\Pages\RegistrarCliente;
use App\Http\Controllers\ClienteController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/clientes/create');
});

// Route::get('/registrar-cliente', function () {
//     // Se instancia la página y se renderiza su contenido
//     return app(RegistrarCliente::class)->render();
// })
// ->name('registrar.cliente')
// ->withoutMiddleware([
//     // Aquí se listan los middleware que deseas excluir, por ejemplo:
//     Authenticate::class,
//     // Si Filament usa otro middleware específico (por ejemplo, 'auth:filament'), agrégalo aquí:
//     // 'auth:filament'
// ]);

Route::get('/clientes/create', [ClienteController::class, 'create'])->name('clientes.create');
Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
