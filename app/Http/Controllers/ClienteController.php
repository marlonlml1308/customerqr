<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Customers;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

class ClienteController extends Controller
{
    // Muestra el formulario
    public function create()
    {
        return view('clientes.create');
    }

    // Procesa el envío del formulario
    public function store(Request $request)
    {
        // dd($request->all());
        // Validación de campos básicos y captcha
        $validator = Validator::make($request->all(), [
            'nombre'   => 'required',
            'numero_documento' => 'required|unique:customers,numero_documento',
            'tipo_documento'   => 'required',
            'correo'           => 'required|email|',
            // 'direccion'        => 'required',
            'g-recaptcha-response' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors('Error: numero de documento ya registrado!')->withInput();
        }

        // Validar el captcha (usando la API de Google reCAPTCHA)
        $secretKey = env('RECAPTCHA_SECRET_KEY');
        $response = $request->input('g-recaptcha-response');
        $remoteIp = $request->ip();

        $client = new Client();
        $verifyResponse = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => [
                'secret'   => $secretKey,
                'response' => $response,
                'remoteip' => $remoteIp,
            ]
        ]);
        $responseData = json_decode($verifyResponse->getBody());

        if (!$responseData->success) {
            return redirect()->back()->withErrors(['captcha' => 'Error al validar el captcha, intenta de nuevo.'])->withInput();
        }

        // Si pasa la validación, guardar el cliente
        Customers::create([
            'nombre'           => $request->nombre,
            'numero_documento' => $request->numero_documento,
            'tipo_documento'   => $request->tipo_documento,
            'correo'           => $request->correo,
            'direccion'        => $request->direccion,
        ]);

        return redirect()->back()->with('success', '¡Datos guardados correctamente!');
    }
}
