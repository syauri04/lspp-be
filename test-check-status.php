<?php

$clientId = 'BRN-0295-1788235272152';
$secretKey = 'SK-cDlegqq4pearJRk7VHRN';
$isProduction = false;

$invoiceNumber = 'LSPP-4C78404D-1788240716'; // ganti sesuai kebutuhan

$baseUrl = $isProduction ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
$path = "/orders/v1/status/{$invoiceNumber}";

$requestId = bin2hex(random_bytes(16));
$timestamp = gmdate('Y-m-d\TH:i:s\Z');

// GET method: TIDAK pakai Digest sama sekali (beda dari POST)
$components = implode("\n", [
    'Client-Id:' . $clientId,
    'Request-Id:' . $requestId,
    'Request-Timestamp:' . $timestamp,
    'Request-Target:' . $path,
]);

$signature = 'HMACSHA256=' . base64_encode(
    hash_hmac('sha256', $components, $secretKey, true)
);

$ch = curl_init($baseUrl . $path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Client-Id: ' . $clientId,
        'Request-Id: ' . $requestId,
        'Request-Timestamp: ' . $timestamp,
        'Signature: ' . $signature,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";
