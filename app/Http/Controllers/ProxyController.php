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

        Log::info('Iniciando búsqueda de cliente', ['document' => $docNumber]);

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
            Log::debug('Token generado exitosamente');

            $urlcustomer = "{$apiUrl}/customer/GetAll";
            Log::info('Llamando API', ['url' => $urlcustomer]);

            $customersResponse = Http::withToken($token)->get($urlcustomer);

            Log::info('Respuesta recibida', [
                'status' => $customersResponse->status(),
                'successful' => $customersResponse->successful()
            ]);

            if (!$customersResponse->successful()) {
                Log::error('Error en respuesta de API', [
                    'status' => $customersResponse->status(),
                    'body' => $customersResponse->body()
                ]);
                return response()->json([
                    'error' => 'Failed to fetch customers',
                    'details' => $customersResponse->body()
                ], 500);
            }

            $todaLaData = $customersResponse->json();
            $customers = $todaLaData['data'] ?? [];

            Log::debug('Datos parseados', [
                'total_customers' => is_array($customers) ? count($customers) : 0,
                'structure' => is_array($todaLaData) ? array_keys($todaLaData) : 'no es array'
            ]);

            if (!is_array($customers)) {
                Log::warning('Estructura de datos inválida', ['data' => $todaLaData]);
                return response()->json(['found' => false, 'message' => 'Invalid data structure from API']);
            }

            // Find the customer
            $found = null;
            foreach ($customers as $c) {
                if (isset($c['id_number']) && (string) $c['id_number'] === (string) $docNumber) {
                    $found = $c;
                    Log::info('Cliente encontrado por id_number', ['customer_id' => $c['customerId'] ?? null]);
                    break;
                }
                if (isset($c['customerNo']) && (string) $c['customerNo'] === (string) $docNumber) {
                    $found = $c;
                    Log::info('Cliente encontrado por customerNo', ['customer_id' => $c['customerId'] ?? null]);
                    break;
                }
                if (isset($c['customFields']['id_number']) && (string) $c['customFields']['id_number'] === (string) $docNumber) {
                    $found = $c;
                    Log::info('Cliente encontrado por customFields.id_number', ['customer_id' => $c['customerId'] ?? null]);
                    break;
                }
            }

            if ($found) {
                Log::info('Cliente encontrado exitosamente', ['document' => $docNumber]);
                return response()->json([
                    'found' => true,
                    'data' => [
                        'nombre' => trim(($found['firstName'] ?? '') . ' ' . ($found['surname'] ?? '')),
                        'correo' => $found['email'] ?? '',
                        'customerId' => $found['customerId'] ?? null,
                    ],
                    'debug' => [
                        'api_url' => "{$apiUrl}/customer/GetAll",
                        'api_response_status' => $customersResponse->status(),
                        'full_api_response' => $todaLaData
                    ]
                ]);
            } else {
                Log::warning('Cliente no encontrado', [
                    'document' => $docNumber,
                    'total_searched' => count($customers)
                ]);
                return response()->json([
                    'found' => false,
                    'debug' => [
                        'api_url' => "{$apiUrl}/customer/GetAll",
                        'api_response_status' => $customersResponse->status(),
                        'full_api_response' => $todaLaData
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en getCustomerByDocument', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
                //      'id_number' => $request->input('numero_documento'),
                //      'identificationType' => $request->input('tipo_documento')
                // ],
                'isActive' => true,
                'isCompany' => false,
                'branchId' => config('services.cizaro.branch_id', 1), // Agregar branch ID
                'companyId' => config('services.cizaro.company_id', 1), // Agregar company ID
            ];

            Log::debug('Payload preparado', ['payload' => $payload]);

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
}