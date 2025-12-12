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
        Log::info('Inicio de proceso de registro/actualización de cliente', [
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

        // Determinar si es Create o Update
        $customerId = $request->input('customer_id');
        $docNumber = $request->numero_documento;

        // Si no viene customer_id, verificamos si existe en la API (Update implícito para robustez)
        if (empty($customerId)) {
            try {
                Log::info('Verificando existencia en API para determinar acción', ['documento' => $docNumber]);

                $checkRequest = Request::create('/proxy/get-customer', 'GET', ['document' => $docNumber]);
                $checkResponse = $this->proxyController->getCustomerByDocument($checkRequest);
                $checkResult = json_decode($checkResponse->getContent(), true);

                if ($checkResult['found'] ?? false) {
                    $customerId = $checkResult['data']['customerId'] ?? null;
                    Log::info('Cliente ya existe, cambiando flujo a Update', ['customerId' => $customerId]);
                }
            } catch (\Exception $e) {
                Log::warning('Error verificando cliente y no hay ID, se intentará crear', ['error' => $e->getMessage()]);
            }
        }

        if ($customerId) {
            // --- ACTUALIZACIÓN (PUT) ---
            try {
                Log::info('Ejecutando actualización de cliente', ['customerId' => $customerId]);

                $updateRequest = Request::create('/proxy/update-customer', 'PUT', [
                    'customerId' => $customerId,
                    'nombre' => $request->nombre,
                    'numero_documento' => $request->numero_documento,
                    'tipo_documento' => $request->tipo_documento,
                    'correo' => $request->correo,
                ]);

                $apiResponse = $this->proxyController->updateCustomer($updateRequest);
                $apiResult = json_decode($apiResponse->getContent(), true);

                $statusCode = $apiResponse->getStatusCode();
                $isSuccessfulHttp = ($statusCode >= 200 && $statusCode < 300);

                // Verificar éxito lógico de API 
                $apiData = $apiResult['data'] ?? [];

                // La API real devuelve "isSuccessful" dentro de "data" o en el root?
                // Según logs anteriores: "data": { ... "isSuccessful": true ... } no, wait.
                // En el XML request del usuario:
                // "isSuccessful": true,
                // "message": "...",
                // "data": { ... }
                // O sea isSuccessful está en el root del JSON de la API externa.

                // ProxyController devuelve: 'data' => $response->json().
                // Asi que $apiResult['data'] contiene la respuesta completa de la API externa.
                // Entonces $apiResult['data']['isSuccessful'] debería ser el valor.

                $apiExternalResponse = $apiResult['data'] ?? [];
                $isLogicSuccess = $apiExternalResponse['isSuccessful'] ?? false;

                if ($isSuccessfulHttp && ($apiResult['success'] ?? false) && $isLogicSuccess) {
                    Log::info('Cliente actualizado correctamente');
                    return redirect()->back()->with('success', '¡Datos del cliente actualizados correctamente!');
                } else {
                    $msg = $apiExternalResponse['message'] ?? 'Error desconocido al actualizar';
                    Log::error('Fallo en actualización', ['response' => $apiResult]);
                    return redirect()->back()->withErrors(['api' => "Error al actualizar: {$msg}"])->withInput();
                }

            } catch (\Exception $e) {
                Log::error('Excepción al actualizar', ['error' => $e->getMessage()]);
                return redirect()->back()->withErrors(['api' => 'Error procesando la actualización.'])->withInput();
            }

        } else {
            // --- CREACIÓN (POST) ---
            try {
                Log::info('Ejecutando creación de nuevo cliente');

                $createRequest = Request::create('/proxy/create-customer', 'POST', [
                    'nombre' => $request->nombre,
                    'numero_documento' => $request->numero_documento,
                    'tipo_documento' => $request->tipo_documento,
                    'correo' => $request->correo,
                ]);

                $apiResponse = $this->proxyController->createCustomer($createRequest);
                $apiResult = json_decode($apiResponse->getContent(), true);

                $statusCode = $apiResponse->getStatusCode();
                $isSuccessfulHttp = ($statusCode >= 200 && $statusCode < 300);

                // $apiResult['data'] es la respuesta de la API externa (proxy)
                $apiExternalResponse = $apiResult['data'] ?? [];
                $isLogicSuccess = $apiExternalResponse['isSuccessful'] ?? false;
                $apiMessage = $apiExternalResponse['message'] ?? 'Error desconocido';

                if ($isSuccessfulHttp && ($apiResult['success'] ?? false) && $isLogicSuccess) {
                    Log::info('Cliente creado exitosamente');
                    return redirect()->back()->with('success', '¡Cliente registrado correctamente en el sistema!');
                } else {
                    Log::error('Fallo en creación', ['msg' => $apiMessage, 'full' => $apiResult]);
                    return redirect()->back()->withErrors(['api' => "Error al registrar el cliente: {$apiMessage}"])->withInput();
                }

            } catch (\Exception $e) {
                Log::error('Excepción al crear', ['error' => $e->getMessage()]);
                return redirect()->back()->withErrors(['api' => 'Error de comunicación con el sistema.'])->withInput();
            }
        }
    }
}