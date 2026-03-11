<?php
/**
 * One-time maintenance script: clear avatar_path for non-custom-avatar users
 *
 * For every user where use_custom_avatar = 0, this script sets avatar_path to NULL.
 * On the next login the callback.php handler will call syncEntraData() / cacheEntraPhoto()
 * and write a fresh copy of the Entra ID profile photo back into avatar_path.
 *
 * Usage (CLI):
 *   php fix_avatars.php
 *
 * Usage (HTTP – requires CRON_TOKEN from .env):
 *   https://your-domain.example/fix_avatars.php?token=<CRON_TOKEN>
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
    $__cronToken = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    if ($__cronToken === '' || !isset($_GET['token']) || !is_string($_GET['token']) || !hash_equals($__cronToken, $_GET['token'])) {
        http_response_code(403);
        exit('Forbidden.' . PHP_EOL);
    }
    unset($__cronToken);
}

// Plain-text output
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== fix_avatars.php ===" . PHP_EOL;
echo "Started at: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    $db = Database::getUserDB();

    // How many users are affected?
    $countStmt = $db->query("SELECT COUNT(*) FROM users WHERE use_custom_avatar = 0 AND avatar_path IS NOT NULL");
    $affected  = (int) $countStmt->fetchColumn();

    echo "Users with use_custom_avatar = 0 and a non-NULL avatar_path: {$affected}" . PHP_EOL;

    if ($affected === 0) {
        echo "Nothing to do." . PHP_EOL;
    } else {
        // Clear avatar_path so the next login triggers a fresh Entra ID photo sync.
        $updateStmt = $db->prepare("UPDATE users SET avatar_path = NULL WHERE use_custom_avatar = 0 AND avatar_path IS NOT NULL");
        $updateStmt->execute();
        $rows = $updateStmt->rowCount();
        echo "avatar_path cleared for {$rows} user(s)." . PHP_EOL;
    }

    echo PHP_EOL . "Done at: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo "=== Remember to DELETE this file after use ===" . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    error_log('[fix_avatars] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
    exit(1);
}
