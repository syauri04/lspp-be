<?php

/**
 * Simulasi HTTP Notification dari DOKU, dikirim LANGSUNG ke endpoint local kamu.
 * Tidak perlu ngrok -- ini generate payload + signature yang valid sendiri,
 * lalu POST ke http://127.0.0.1:8000/api/doku/callback.
 *
 * Ganti $invoiceNumber di bawah dengan kode_transaksi asli dari pembayaran
 * yang mau kamu test (cek di tabel `pembayaran` / dari response checkout kamu).
 *
 * Jalankan: php test-webhook-doku-local.php
 */

$secretKey = 'SK-cDlegqq4pearJRk7VHRN'; // copy dari .env kamu
$localWebhookPath = '/api/doku/callback'; // Request-Target = path MERCHANT, bukan path DOKU

$invoiceNumber = 'LSPP-4C78404D-1788240716'; // <-- WAJIB DIGANTI

$payload = [
    'service' => ['id' => 'VIRTUAL_ACCOUNT'],
    'acquirer' => ['id' => 'BCA'],
    'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
    'transaction' => [
        'status' => 'SUCCESS',
        'date' => gmdate('Y-m-d\TH:i:s\Z'),
        'original_request_id' => bin2hex(random_bytes(16)),
    ],
    'order' => [
        'invoice_number' => $invoiceNumber,
        'amount' => 3000000,
    ],
];

$bodyJson = json_encode($payload);

$clientId = 'BRN-0295-1788235272152'; // copy dari .env kamu
$requestId = bin2hex(random_bytes(16));
$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$digest = base64_encode(hash('sha256', $bodyJson, true));

$components = implode("\n", [
    'Client-Id:' . $clientId,
    'Request-Id:' . $requestId,
    'Request-Timestamp:' . $timestamp,
    'Request-Target:' . $localWebhookPath,
    'Digest:' . $digest,
]);

$signature = 'HMACSHA256=' . base64_encode(
    hash_hmac('sha256', $components, $secretKey, true)
);

echo "=== Mengirim simulasi webhook untuk invoice: {$invoiceNumber} ===\n";
echo "Body: {$bodyJson}\n\n";

$ch = curl_init('http://127.0.0.1:8000' . $localWebhookPath);
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
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";
