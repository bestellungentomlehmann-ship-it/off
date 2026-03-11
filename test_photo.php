<?php

// Azure Daten DIREKT im Script
$tenantId = "01310861-1145-4b8f-8f74-f4362a90b3f0";
$clientId = "a911e088-0b5a-4515-8d89-f72b5a74ea16";
$clientSecret = "ZTl8Q~EgXC7HdlOIfruIXd_S2aqvEoV.nOiUicup";

// User dessen Bild geladen wird
$userEmail = "tom.lehmann@business-consulting.de";


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
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data["access_token"])) {
    die("TOKEN ERROR:\n".$response);
}

$token = $data["access_token"];


/**
 * FOTO ABRUFEN
 */
$photoUrl = "https://graph.microsoft.com/v1.0/users/$userEmail/photo/\$value";

$ch = curl_init($photoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer ".$token
    ]
]);

$image = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http != 200) {
    die("PHOTO ERROR HTTP CODE: ".$http);
}

header("Content-Type: image/jpeg");
echo $image;