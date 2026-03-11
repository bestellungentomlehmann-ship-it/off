<?php
/**
 * One-time maintenance script: reset avatar sync flags for a specific user.
 *
 * Sets use_custom_avatar = 0 and avatar_path = NULL for the user
 * 'tom.lehmann@business-consulting.de'. On the next login the AuthHandler
 * will call syncEntraData() / cacheEntraPhoto() and write a fresh copy of
 * the Entra ID profile photo back into avatar_path.
 *
 * Usage (CLI):
 *   php reset_sync.php tom.lehmann@business-consulting.de
 *
 * Usage (HTTP – requires CRON_TOKEN from .env):
 *   https://your-domain.example/reset_sync.php?token=<CRON_TOKEN>&email=tom.lehmann@business-consulting.de
 *
 * DELETE THIS FILE after it has been executed successfully.
 */

// Load configuration and database helpers
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';

// -------------------------------------------------------------------------
// Access control
// CLI is always allowed; HTTP access requires a valid CRON_TOKEN.
// -------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    $__cronToken    = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    $__givenToken   = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
    $__tokenInvalid = $__cronToken === '' || !hash_equals($__cronToken, $__givenToken);

    if ($__tokenInvalid) {
        http_response_code(403);
        exit('Forbidden.' . PHP_EOL);
    }
    unset($__cronToken, $__givenToken, $__tokenInvalid);
}

// Plain-text output
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// -------------------------------------------------------------------------
// Target user: pass the email as a CLI argument to avoid hardcoding PII.
// Example:  php reset_sync.php tom.lehmann@business-consulting.de
// -------------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    $targetEmail = $argv[1] ?? null;
    if (!$targetEmail) {
        echo "Usage: php reset_sync.php <email>" . PHP_EOL;
        exit(1);
    }
} else {
    // HTTP: email supplied via GET parameter (already protected by CRON_TOKEN above).
    $targetEmail = isset($_GET['email']) && is_string($_GET['email']) ? trim($_GET['email']) : null;
    if (!$targetEmail) {
        http_response_code(400);
        exit('Missing required parameter: email' . PHP_EOL);
    }
}

echo "=== reset_sync.php ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    $db = Database::getUserDB();

    // Look up the user to confirm they exist
    $checkStmt = $db->prepare("SELECT id, email, use_custom_avatar, avatar_path FROM users WHERE email = ?");
    $checkStmt->execute([$targetEmail]);
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "No user found with email: " . $targetEmail . PHP_EOL;
        echo "Nothing to do." . PHP_EOL;
    } else {
        echo "Found user: id={$user['id']}, email={$user['email']}" . PHP_EOL;
        echo "  use_custom_avatar (before): {$user['use_custom_avatar']}" . PHP_EOL;
        echo "  avatar_path (before):       " . ($user['avatar_path'] ?? 'NULL') . PHP_EOL . PHP_EOL;

        $updateStmt = $db->prepare("UPDATE users SET use_custom_avatar = 0, avatar_path = NULL WHERE email = ?");
        $updateStmt->execute([$targetEmail]);
        $rows = $updateStmt->rowCount();

        echo "Rows updated: {$rows}" . PHP_EOL;
        echo "  use_custom_avatar set to: 0" . PHP_EOL;
        echo "  avatar_path set to:       NULL" . PHP_EOL;
        echo PHP_EOL . "The next login will trigger a fresh Entra ID photo sync via AuthHandler." . PHP_EOL;
    }

    echo PHP_EOL . "Done at: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo "=== Remember to DELETE this file after use ===" . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    error_log('[reset_sync] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
    exit(1);
}
