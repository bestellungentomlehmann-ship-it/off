<?php
/**
 * test_photo.php – Quick sanity-check for the Microsoft Graph profile-photo endpoint.
 *
 * Reads credentials from the application configuration (config/config.php) and
 * fetches the profile photo for a given e-mail address using the Client Credentials
 * flow. Useful for verifying that Azure credentials and permissions are correct.
 *
 * Usage: ?email=user@example.com
 */

require_once __DIR__ . '/config/config.php';

$tenantId     = defined('AZURE_TENANT_ID')     ? AZURE_TENANT_ID     : '';
$clientId     = defined('AZURE_CLIENT_ID')     ? AZURE_CLIENT_ID     : '';
$clientSecret = defined('AZURE_CLIENT_SECRET') ? AZURE_CLIENT_SECRET : '';

$userEmail = isset($_GET['email']) ? trim($_GET['email']) : 'it@business-consulting.de';

if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
    die("Azure credentials missing in configuration.");
}

/**
 * ACCESS TOKEN HOLEN
 */
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

$postFields = http_build_query([
    "client_id"     => $clientId,
    "client_secret" => $clientSecret,
    "scope"         => "https://graph.microsoft.com/.default",
    "grant_type"    => "client_credentials"
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST          => true,
    CURLOPT_POSTFIELDS    => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER    => ["Content-Type: application/x-www-form-urlencoded"]
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data["access_token"])) {
    die("TOKEN ERROR:\n" . htmlspecialchars($response));
}

$token = $data["access_token"];

/**
 * FOTO ABRUFEN
 */
$photoUrl = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($userEmail) . "/photo/\$value";

$ch = curl_init($photoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer " . $token]
]);

$image = curl_exec($ch);
$http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http != 200) {
    die("PHOTO ERROR HTTP CODE: " . $http);
}

header("Content-Type: image/jpeg");
echo $image;
