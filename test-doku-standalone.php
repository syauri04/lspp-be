<?php

/**
 * Test langsung ke DOKU Checkout API, TANPA lewat Laravel sama sekali.
 * Tujuan: isolasi apakah error "invalid_client_id" itu soal kredensial,
 * atau ada bug di cara CheckoutController hitung signature.
 *
 * Jalankan: php test-doku-standalone.php
 */

$clientId = 'BRN-0242-1787033021409';   // copy persis dari .env kamu
$secretKey = 'SK-3ozY78QTl6dQvgq3wyld'; // copy persis dari .env kamu
$isProduction = false;

$baseUrl = $isProduction ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
$path = '/checkout/v1/payment';

$requestId = bin2hex(random_bytes(16));
$timestamp = gmdate('Y-m-d\TH:i:s\Z');

$body = [
    'order' => [
        'amount' => 100000,
        'invoice_number' => 'TEST-' . time(),
        'callback_url' => 'https://example.com/callback',
        'auto_redirect' => true,
    ],
    'payment' => [
        'payment_due_date' => 60,
    ],
    'customer' => [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ],
];

$bodyJson = json_encode($body);
$digest = base64_encode(hash('sha256', $bodyJson, true));

$signatureComponents = implode("\n", [
    'Client-Id:' . $clientId,
    'Request-Id:' . $requestId,
    'Request-Timestamp:' . $timestamp,
    'Request-Target:' . $path,
    'Digest:' . $digest,
]);

$signature = 'HMACSHA256=' . base64_encode(
    hash_hmac('sha256', $signatureComponents, $secretKey, true)
);

echo "=== Request yang dikirim ===\n";
echo "URL: {$baseUrl}{$path}\n";
echo "Client-Id: {$clientId}\n";
echo "Request-Id: {$requestId}\n";
echo "Request-Timestamp: {$timestamp}\n";
echo "Digest: {$digest}\n";
echo "Signature: {$signature}\n";
echo "Body: {$bodyJson}\n\n";

$ch = curl_init($baseUrl . $path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $bodyJson,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Client-Id: ' . $clientId,
        'Request-Id: ' . $requestId,
        'Request-Timestamp: ' . $timestamp,
        'Signature: ' . $signature,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "=== Response ===\n";
echo "HTTP Code: {$httpCode}\n";
if ($curlError) {
    echo "cURL Error: {$curlError}\n";
}
echo "Body: {$response}\n";
