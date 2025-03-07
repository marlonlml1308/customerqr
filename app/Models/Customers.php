<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customers extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'nombre',
        'numero_documento',
        'tipo_documento',
        'correo',
        'direccion'
    ];
}
