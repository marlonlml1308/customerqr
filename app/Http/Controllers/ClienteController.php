<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Customers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class ClienteController extends Controller
{
    protected $proxyController;

    public function __construct(ProxyController $proxyController)
    {
        $this->proxyController = $proxyController;
    }

    // Muestra el formulario
    public function create()
    {
        return view('clientes.create');
    }

    // Procesa el envío del formulario
    public function store(Request $request)
    {
        Log::info('Inicio de proceso de registro de cliente', [
            'datos_recibidos' => $request->except('g-recaptcha-response')
        ]);

        // Validación de campos básicos y captcha
        $validator = Validator::make($request->all(), [
            'nombre' => 'required',
            'numero_documento' => 'required',
            'tipo_documento' => 'required',
            'correo' => 'required|email',
            'g-recaptcha-response' => 'required',
        ]);

        if ($validator->fails()) {
            Log::warning('Validación fallida', ['errores' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Validar el captcha
        $secretKey = config('services.recaptcha.secret');
        $response = $request->input('g-recaptcha-response');
        $remoteIp = $request->ip();

        try {
            $client = new Client();
            $verifyResponse = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $response,
                    'remoteip' => $remoteIp,
                ]
            ]);
            $responseData = json_decode($verifyResponse->getBody());

            if (!$responseData->success) {
                Log::warning('Captcha inválido', ['score' => $responseData->score ?? 'N/A']);
                return redirect()->back()
                    ->withErrors(['captcha' => 'Error al validar el captcha, intenta de nuevo.'])
                    ->withInput();
            }

            Log::info('Captcha validado correctamente', ['score' => $responseData->score ?? 'N/A']);

        } catch (\Exception $e) {
            Log::error('Error validando captcha', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->withErrors(['captcha' => 'Error en la validación del captcha.'])
                ->withInput();
        }

        // Verificar si el cliente ya existe en la API (llamada directa al controller)
        try {
            Log::info('Verificando si el cliente existe en la API', [
                'documento' => $request->numero_documento
            ]);

            // Crear un Request simulado para el ProxyController
            $checkRequest = Request::create('/proxy/get-customer', 'GET', [
                'document' => $request->numero_documento
            ]);

            $checkResponse = $this->proxyController->getCustomerByDocument($checkRequest);
            $checkResult = json_decode($checkResponse->getContent(), true);

            if ($checkResult['found'] ?? false) {
                Log::warning('Cliente ya existe en la API', [
                    'documento' => $request->numero_documento,
                    'cliente' => $checkResult['data'] ?? null
                ]);

                return redirect()->back()
                    ->withErrors(['numero_documento' => 'Este número de documento ya está registrado en el sistema.'])
                    ->withInput();
            }

            Log::info('Cliente no existe en API, procediendo con creación');

        } catch (\Exception $e) {
            Log::error('Error verificando cliente existente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Continuamos con la creación aunque falle la verificación
        }

        // Enviar datos a la API externa (llamada directa al ProxyController)
        try {
            Log::info('Enviando datos a la API externa');

            // Crear un Request simulado para createCustomer
            $createRequest = Request::create('/proxy/create-customer', 'POST', [
                'nombre' => $request->nombre,
                'numero_documento' => $request->numero_documento,
                'tipo_documento' => $request->tipo_documento,
                'correo' => $request->correo,
            ]);

            $apiResponse = $this->proxyController->createCustomer($createRequest);
            $apiResult = json_decode($apiResponse->getContent(), true);

            Log::info('Respuesta completa de la API', [
                'status' => $apiResponse->status(),
                'full_response' => $apiResult
            ]);

            // Verificar si la API realmente creó el cliente
            $apiData = $apiResult['data'] ?? [];
            $isSuccessful = $apiData['isSuccessful'] ?? false;
            $apiMessage = $apiData['message'] ?? 'Error desconocido en la API';
            $apiStatusCode = $apiData['statusCode'] ?? null;

            if ($apiResponse->status() >= 200 && $apiResponse->status() < 300 && $isSuccessful) {
                Log::info('Cliente creado exitosamente en la API', [
                    'documento' => $request->numero_documento,
                    'api_message' => $apiMessage
                ]);

                return redirect()->back()->with('success', '¡Cliente registrado correctamente en el sistema!');
            } else {
                // La API respondió 200 pero isSuccessful = false
                Log::error('La API retornó error en el contenido', [
                    'status' => $apiResponse->status(),
                    'isSuccessful' => $isSuccessful,
                    'message' => $apiMessage,
                    'statusCode' => $apiStatusCode,
                    'full_response' => $apiResult
                ]);

                return redirect()->back()
                    ->withErrors(['api' => "Error al registrar el cliente: {$apiMessage}"])
                    ->withInput();
            }

        } catch (\Exception $e) {
            Log::error('Excepción al comunicarse con la API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['api' => 'Error de comunicación con el sistema. Por favor, intenta más tarde.'])
                ->withInput();
        }
    }
}