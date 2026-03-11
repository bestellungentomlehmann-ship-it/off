<?php
/**
 * Debug script: Reset avatar and re-sync Entra profile photo for a specific user.
 *
 * Usage: php debug_fix_my_avatar.php
 *        or open in browser (protected by .htaccess or remove after use)
 */

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/handlers/AuthHandler.php';

$targetEmail = 'tom.lehmann@business-consulting.de';

$db = Database::getUserDB();

// 1. Fetch user record
$stmt = $db->prepare("SELECT id, email, first_name, last_name, azure_oid, entra_roles, avatar_path, use_custom_avatar FROM users WHERE email = ?");
$stmt->execute([$targetEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Fehler: Benutzer '{$targetEmail}' wurde nicht gefunden.\n";
    exit(1);
}

echo "Benutzer gefunden: ID={$user['id']}, E-Mail={$user['email']}\n";
echo "Aktuell: avatar_path=" . var_export($user['avatar_path'], true) . ", use_custom_avatar={$user['use_custom_avatar']}\n";

// 2. Reset avatar_path to NULL and use_custom_avatar to 0
$update = $db->prepare("UPDATE users SET avatar_path = NULL, use_custom_avatar = 0 WHERE id = ?");
$update->execute([$user['id']]);
echo "avatar_path auf NULL und use_custom_avatar auf 0 gesetzt.\n";

// 3. Build a minimal $userData array from the existing database record so that
//    syncEntraData can extract the mail address (required for the photo fetch).
$userData = [
    'given_name'  => $user['first_name'],
    'family_name' => $user['last_name'],
    'email'       => $user['email'],
    'roles'       => json_decode($user['entra_roles'] ?? '[]', true) ?? [],
];

$azureOid = $user['azure_oid'] ?? '';

// 4. Call AuthHandler::syncEntraData to trigger the Entra photo sync
echo "Starte AuthHandler::syncEntraData für Benutzer ID={$user['id']} …\n";
AuthHandler::syncEntraData($user['id'], $userData, $azureOid);

// 5. Read back the updated avatar_path to report the result
$check = $db->prepare("SELECT avatar_path FROM users WHERE id = ?");
$check->execute([$user['id']]);
$updated = $check->fetch(PDO::FETCH_ASSOC);
$newPath = $updated['avatar_path'] ?? null;

if ($newPath !== null) {
    echo "Erfolg: Profilbild wurde geladen und gespeichert unter: {$newPath}\n";
} else {
    echo "Hinweis: Es wurde kein Entra-Profilbild gefunden oder gespeichert (avatar_path bleibt NULL).\n";
}
