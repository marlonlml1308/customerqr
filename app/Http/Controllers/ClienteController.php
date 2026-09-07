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

                // Log completo para diagnóstico del endpoint de actualización
                $apiExternalResponse = $apiResult['data'] ?? [];
                Log::info('Respuesta completa del Update', [
                    'http_status'   => $statusCode,
                    'proxy_success' => $apiResult['success'] ?? null,
                    'api_response'  => $apiExternalResponse,
                ]);

                // El endpoint PUT puede no devolver "isSuccessful" — se confía en el código HTTP
                $isLogicSuccess = $apiExternalResponse['isSuccessful'] 
                    ?? ($apiResult['success'] ?? false);

                // Pasar respuesta completa a la sesión para console.log en el navegador
                session()->flash('api_debug', [
                    'action'      => 'update',
                    'http_status' => $statusCode,
                    'result'      => $apiResult,
                ]);

                if ($isSuccessfulHttp && ($apiResult['success'] ?? false)) {
                    Log::info('Cliente actualizado correctamente', ['isSuccessful' => $isLogicSuccess]);
                    return redirect()->back()->with('success', '¡Datos del cliente actualizados correctamente!');
                } else {
                    $msg = $apiExternalResponse['message']
                        ?? $apiResult['message']
                        ?? "Error HTTP {$statusCode} al actualizar";
                    Log::error('Fallo en actualización', [
                        'http_status'  => $statusCode,
                        'full_result'  => $apiResult,
                    ]);
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
                Log::info('Respuesta completa del Create', [
                    'http_status'   => $statusCode,
                    'proxy_success' => $apiResult['success'] ?? null,
                    'api_response'  => $apiExternalResponse,
                ]);

                $isLogicSuccess = $apiExternalResponse['isSuccessful']
                    ?? ($apiResult['success'] ?? false);
                $apiMessage = $apiExternalResponse['message']
                    ?? $apiResult['message']
                    ?? "Error HTTP {$statusCode}";

                // Pasar respuesta completa a la sesión para console.log en el navegador
                session()->flash('api_debug', [
                    'action'      => 'create',
                    'http_status' => $statusCode,
                    'result'      => $apiResult,
                ]);

                if ($isSuccessfulHttp && ($apiResult['success'] ?? false)) {
                    Log::info('Cliente creado exitosamente', ['isSuccessful' => $isLogicSuccess]);
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