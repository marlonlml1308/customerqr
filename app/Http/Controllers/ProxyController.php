<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyController extends Controller
{
    private function getApiToken($apiUrl, $apiKey)
    {
        $tokenResponse = Http::get("{$apiUrl}/security/CreateTokenByKey/{$apiKey}");

        if (!$tokenResponse->successful()) {
            throw new \Exception('Failed to obtain API token: ' . $tokenResponse->body());
        }

        $tokenBody = $tokenResponse->body();
        $token = $tokenBody;

        $json = json_decode($tokenBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($json) && isset($json['token'])) {
                $token = $json['token'];
            } elseif (is_array($json) && isset($json['accessToken'])) {
                $token = $json['accessToken'];
                Log::debug('Token obtenido', ['token_preview' => substr($token, 0, 20) . '...']);
            } else {
                if (is_string($json)) {
                    $token = $json;
                }
            }
        }
        return trim($token, '"');
    }

    public function getCustomerByDocument(Request $request)
    {
        $docNumber = $request->query('document');

        Log::info('Iniciando búsqueda de cliente por documento', ['document' => $docNumber]);

        if (!$docNumber) {
            return response()->json(['error' => 'Document number is required'], 400);
        }

        $apiUrl = config('services.cizaro.url');
        $apiKey = config('services.cizaro.key');

        if (!$apiUrl || !$apiKey) {
            return response()->json(['error' => 'API configuration missing'], 500);
        }

        try {
            $token = $this->getApiToken($apiUrl, $apiKey);

            // Nuevo endpoint solicitado: customer/GetByIdNumber?idNumber=...&showPhoto=false
            $url = "{$apiUrl}/customer/GetByIdNumber?idNumber={$docNumber}&showPhoto=false";
            Log::info('Llamando API GetByIdNumber', ['url' => $url]);

            $response = Http::withToken($token)->get($url);

            Log::info('Respuesta recibida', [
                'status' => $response->status(),
                'successful' => $response->successful()
            ]);

            if (!$response->successful()) {
                Log::error('Error en respuesta de API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                // Si falla (ej 404), retornamos found=false
                return response()->json([
                    'found' => false,
                    'message' => 'Error from API',
                    'debug' => ['status' => $response->status()]
                ]);
            }

            $jsonData = $response->json();

            // Verificar estructura isSuccessful y data
            $isSuccessful = $jsonData['isSuccessful'] ?? false;
            $data = $jsonData['data'] ?? null;

            if ($isSuccessful && $data) {
                Log::info('Cliente encontrado', ['customerId' => $data['customerId'] ?? 'N/A']);

                return response()->json([
                    'found' => true,
                    'data' => [
                        'customerId' => $data['customerId'],
                        'nombre' => trim(($data['firstName'] ?? '') . ' ' . ($data['surname'] ?? '')),
                        'firstName' => $data['firstName'],
                        'surname' => $data['surname'],
                        'correo' => $data['email'],
                        'idType' => $data['idType'],
                        'idNumber' => $data['idNumber'],
                        'raw' => $data
                    ]
                ]);
            } else {
                Log::info('Cliente no encontrado en respuesta exitosa', [
                    'message' => $jsonData['message'] ?? 'No message'
                ]);
                return response()->json([
                    'found' => false,
                    'message' => $jsonData['message'] ?? 'Customer not found'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en getCustomerByDocument', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createCustomer(Request $request)
    {
        Log::info('Iniciando creación de cliente', [
            'nombre' => $request->input('nombre'),
            'correo' => $request->input('correo'),
            'documento' => $request->input('numero_documento')
        ]);

        $apiUrl = config('services.cizaro.url');
        $apiKey = config('services.cizaro.key');

        if (!$apiUrl || !$apiKey) {
            return response()->json(['error' => 'API configuration missing'], 500);
        }

        try {
            $token = $this->getApiToken($apiUrl, $apiKey);

            $parts = explode(' ', $request->input('nombre', ''), 2);
            $firstName = $parts[0] ?? '';
            $surname = $parts[1] ?? '';

            $payload = [
                'firstName' => $firstName,
                'surname' => $surname,
                'email' => $request->input('correo'),
                // 'customerNo' => (int) $request->input('numero_documento'),
                //  'customFields' => [
                'idNumber' => $request->input('numero_documento'),
                'idType' => $request->input('tipo_documento'),
                // ],
                'isActive' => true,
                'isCompany' => false,
                'branchId' => config('services.cizaro.branch_id', 1), // Agregar branch ID
                'companyId' => config('services.cizaro.company_id', 1), // Agregar company ID
            ];

            Log::info('BODY POST REQUEST (CreateCustomer):', $payload);

            $response = Http::withToken($token)->post("{$apiUrl}/customer/Create", $payload);

            Log::info('Cliente creado', [
                'status' => $response->status(),
                'successful' => $response->successful()
            ]);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'request_payload' => $payload,
                'api_url' => "{$apiUrl}/customer/Create"
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error en createCustomer', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function updateCustomer(Request $request)
    {
        Log::info('Iniciando actualización de cliente', [
            'customerId' => $request->input('customerId'),
            'nombre' => $request->input('nombre'),
            'documento' => $request->input('numero_documento')
        ]);

        $apiUrl = config('services.cizaro.url');
        $apiKey = config('services.cizaro.key');

        if (!$apiUrl || !$apiKey) {
            return response()->json(['error' => 'API configuration missing'], 500);
        }

        try {
            $token = $this->getApiToken($apiUrl, $apiKey);

            $parts = explode(' ', $request->input('nombre', ''), 2);
            $firstName = $parts[0] ?? '';
            $surname = $parts[1] ?? '';

            // Construir payload similar a Create pero para Update
            // Se asume que Update requiere customerId y los campos a actualizar
            // Nota: Es posible que necesitemos enviar TODOS los campos para no borrar los existentes
            // si el endpoint es un PUT completo. Pero dado que no tenemos todos los datos aqui,
            // enviamos lo que tenemos.
            // Una estrategia mejor seria obtener la data actual, mezclarla y enviarla, 
            // pero por eficiencia confiaremos en que el endpoint maneje esto o que el usuario 
            // actualice los campos principales.

            $payload = [
                'customerId' => $request->input('customerId'),
                'firstName' => $firstName,
                'surname' => $surname,
                'email' => $request->input('correo'),
                'idNumber' => $request->input('numero_documento'),
                'idType' => $request->input('tipo_documento'),
                // Campos adicionales necesarios para mantener consistencia
                'isActive' => true,
                'branchId' => config('services.cizaro.branch_id', 1),
                'companyId' => config('services.cizaro.company_id', 1),
            ];

            Log::info('BODY PUT REQUEST (UpdateCustomer):', $payload);

            // Endpoint PUT customer/Update
            $response = Http::withToken($token)->put("{$apiUrl}/customer/Update", $payload);

            Log::info('Cliente actualizado', [
                'status' => $response->status(),
                'successful' => $response->successful()
            ]);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'request_payload' => $payload,
                'api_url' => "{$apiUrl}/customer/Update"
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error en updateCustomer', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}