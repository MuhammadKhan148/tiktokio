<?php
/**
 * Proxy endpoint to get JWT tokens from FastAPI
 * This allows the frontend to get tokens without CORS issues
 */

session_start();

// Set JSON header
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Enable CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';

// Check if we have a valid token in session
if (isset($_SESSION['api_token']) && isset($_SESSION['api_token_expires'])) {
    // Token is still valid (with 1 hour buffer)
    if ($_SESSION['api_token_expires'] > (time() + 3600)) {
        echo json_encode([
            'token' => $_SESSION['api_token'],
            'expires_at' => date('c', $_SESSION['api_token_expires']),
            'expires_in' => $_SESSION['api_token_expires'] - time(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Fetch new token from FastAPI
$settings = get_site_settings_cached($conn);
$baseUrl = rtrim($settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000', '/');
$url = $baseUrl . '/token';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: TikTokIO-MediaBridge/1.0',
    ],
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to connect to API: ' . $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($statusCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        // Store in session
        $_SESSION['api_token'] = $data['token'];
        if (isset($data['expires_at'])) {
            $_SESSION['api_token_expires'] = strtotime($data['expires_at']);
        } else {
            $_SESSION['api_token_expires'] = time() + ($data['expires_in'] ?? 86400);
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Error response
http_response_code($statusCode ?: 500);
echo json_encode([
    'error' => 'Failed to get token',
    'details' => $response ?: 'Unknown error'
], JSON_UNESCAPED_UNICODE);

