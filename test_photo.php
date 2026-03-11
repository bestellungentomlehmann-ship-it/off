<?php

// Konfiguration über Environment Variablen
$tenantId = getenv('AZURE_TENANT_ID');
$clientId = getenv('AZURE_CLIENT_ID');
$clientSecret = getenv('AZURE_CLIENT_SECRET');
$userEmail = getenv('AZURE_USER_EMAIL');

if (!$tenantId || !$clientId || !$clientSecret || !$userEmail) {
    http_response_code(500);
    die("Missing environment variables.");
}


/**
 * ACCESS TOKEN HOLEN
 */
$tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";

$postFields = http_build_query([
    "client_id" => $clientId,
    "client_secret" => $clientSecret,
    "scope" => "https://graph.microsoft.com/.default",
    "grant_type" => "client_credentials"
]);

$ch = curl_init($tokenUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded"
    ]
]);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(500);
    die("Token request failed: " . curl_error($ch));
}

curl_close($ch);

$data = json_decode($response, true);

if (!isset($data["access_token"])) {
    http_response_code(500);
    die("Token error: " . $response);
}

$token = $data["access_token"];


/**
 * FOTO ABRUFEN
 */
$photoUrl = "https://graph.microsoft.com/v1.0/users/$userEmail/photo/\$value";

$ch = curl_init($photoUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token"
    ]
]);

$image = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($image === false) {
    http_response_code(500);
    die("Photo request failed: " . curl_error($ch));
}

curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    die("Photo error HTTP code: $httpCode");
}

header("Content-Type: image/jpeg");
echo $image;