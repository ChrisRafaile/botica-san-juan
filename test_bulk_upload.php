<?php

// Test script for bulk product upload API using curl

// Test data
$productos = [
    [
        'nombre' => 'Test Paracetamol',
        'concentracion' => '500mg',
        'adicional' => 'Tabletas',
        'laboratorio' => 'Test Lab',
        'presentacion' => 'Blíster de 20',
        'tipo' => 'Analgésico',
        'categoria' => 'Medicamentos',
        'stock' => 50,
        'precio' => 5.50,
        'imagen' => 'images/default_image.png'
    ],
    [
        'nombre' => 'Test Ibuprofeno',
        'concentracion' => '400mg',
        'adicional' => 'Cápsulas',
        'laboratorio' => 'Test Lab 2',
        'presentacion' => 'Caja de 30',
        'tipo' => 'Antiinflamatorio',
        'categoria' => 'Medicamentos',
        'stock' => 30,
        'precio' => 8.75,
        'imagen' => 'images/default_image.png'
    ]
];

try {
    // First, login to get token
    $loginData = json_encode([
        'dni' => '12345678',
        'password' => '123456'
    ]);

    $ch = curl_init('http://127.0.0.1:8000/api/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $loginResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception('Login request failed: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Login failed with HTTP code: $httpCode, Response: $loginResponse");
    }

    $loginResult = json_decode($loginResponse, true);
    $token = $loginResult['token'] ?? null;

    if (!$token) {
        throw new Exception('No token received from login');
    }

    echo "Login successful. Token: " . substr($token, 0, 20) . "...\n";

    // Now test bulk upload
    $bulkData = json_encode([
        'productos' => $productos
    ]);

    $ch = curl_init('http://127.0.0.1:8000/api/productos/bulk');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bulkData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $bulkResponse = curl_exec($ch);
    $bulkHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception('Bulk upload request failed: ' . curl_error($ch));
    }

    curl_close($ch);

    echo "Bulk upload response:\n";
    echo "Status: $bulkHttpCode\n";

    if ($bulkHttpCode >= 200 && $bulkHttpCode < 300) {
        $data = json_decode($bulkResponse, true);
        echo "Created: " . ($data['created_count'] ?? 0) . "\n";
        echo "Errors: " . ($data['error_count'] ?? 0) . "\n";

        if (isset($data['errors']) && count($data['errors']) > 0) {
            echo "Errors:\n";
            foreach ($data['errors'] as $error) {
                echo "- Index " . ($error['index'] ?? 'unknown') . ": " . ($error['error'] ?? 'Unknown error') . "\n";
            }
        }

        echo "Success! Bulk upload API is working.\n";
    } else {
        echo "Failed with HTTP code: $bulkHttpCode\n";
        echo "Response: $bulkResponse\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}