<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/Newsletter.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$currentUser = Auth::user();
$canManage   = Newsletter::canManage($currentUser['role'] ?? '');

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $canManage) {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');
    $deleteId = (int) ($_POST['newsletter_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            Newsletter::delete($deleteId);
            $_SESSION['success_message'] = 'Newsletter erfolgreich gelöscht.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Fehler beim Löschen des Newsletters.';
        }
    }
    header('Location: index.php');
    exit;
}

$searchQuery  = trim($_GET['q'] ?? '');
$newsletters  = [];
try {
    $newsletters = Newsletter::getAll($searchQuery);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Fehler beim Laden der Newsletter: ' . htmlspecialchars($e->getMessage());
}

$title = 'Newsletter-Archiv - IBC Intranet';
ob_start();
?>

<div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <div class="w-11 h-11 rounded-2xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shadow-sm">
                <i class="fas fa-envelope-open-text text-ibc-blue text-xl"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 tracking-tight">Newsletter-Archiv</h1>
        </div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">Internes Archiv aller versendeten E-Mail-Newsletter</p>
    </div>

    <?php if ($canManage): ?>
    <div>
        <a href="upload.php"
           class="btn-primary w-full sm:w-auto justify-center">
            <i class="fas fa-upload"></i>
            Newsletter hochladen
        </a>
    </div>
    <?php endif; ?>
</div>

<form method="GET" class="mb-6 flex items-stretch gap-2 w-full max-w-lg">
    <input type="text" name="q"
           value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
           placeholder="Newsletter durchsuchen..."
           class="flex-1 rounded-xl border-gray-300 dark:border-gray-700 shadow-sm focus:ring-ibc-green focus:border-ibc-green py-2.5 px-4 text-sm">
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 px-5 rounded-xl bg-ibc-green text-white font-semibold hover:bg-ibc-green-dark transition-colors shadow-sm text-sm">
        <i class="fas fa-search"></i>Suchen
    </button>
    <?php if ($searchQuery !== ''): ?>
    <a href="index.php"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl font-semibold hover:bg-gray-200 dark:hover:bg-gray-700 transition text-sm">
        <i class="fas fa-times"></i>Zurücksetzen
    </a>
    <?php endif; ?>
</form>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="mb-6 flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 rounded-xl text-sm">
    <i class="fas fa-check-circle flex-shrink-0"></i>
    <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="mb-6 flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl text-sm">
    <i class="fas fa-exclamation-circle flex-shrink-0"></i>
    <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
</div>
<?php unset($_SESSION['error_message']); endif; ?>

<?php if (empty($newsletters)): ?>
<div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-12 text-center">
    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
        <i class="fas fa-envelope-open-text text-gray-400 dark:text-gray-600 text-2xl" aria-hidden="true"></i>
    </div>
    <?php if ($searchQuery !== ''): ?>
    <p class="text-gray-600 dark:text-gray-400 font-medium">Keine Newsletter für „<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" gefunden.</p>
    <?php else: ?>
    <p class="text-gray-600 dark:text-gray-400 font-medium">Noch keine Newsletter vorhanden.</p>
    <?php if ($canManage): ?>
    <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Klicken Sie auf „Newsletter hochladen", um den ersten Newsletter hinzuzufügen.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="flex flex-col gap-3">
    <?php foreach ($newsletters as $nl):
        $ext       = strtolower(pathinfo($nl['original_filename'] ?? '', PATHINFO_EXTENSION));
        $iconClass = $ext === 'msg' ? 'fas fa-envelope text-purple-500' : 'fas fa-envelope-open-text text-ibc-blue';
        $sentDate  = $nl['sent_date'] ? date('d.m.Y', strtotime($nl['sent_date'])) : null;
        $uploadedBy = trim(($nl['first_name'] ?? '') . ' ' . ($nl['last_name'] ?? '')) ?: 'Unbekannt';
        $fileSizeKb = round(($nl['file_size'] ?? 0) / 1024, 1);
        $nlId = (int) ($nl['id'] ?? 0);
    ?>
    <div class="group bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md hover:border-ibc-blue/30 dark:hover:border-ibc-blue/30 transition-all duration-200">
        <div class="flex items-start gap-4 p-5">
            <div class="flex-shrink-0 w-11 h-11 bg-blue-50 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                <i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?> text-lg"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug break-words hyphens-auto">
                    <?php echo htmlspecialchars($nl['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <?php if (!empty($nl['description'])): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed break-words hyphens-auto">
                    <?php echo htmlspecialchars($nl['description'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>
                <div class="flex flex-wrap gap-3 mt-2">
                    <?php if ($sentDate): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo htmlspecialchars($sentDate); ?>
                    </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                        <i class="fas fa-file"></i>
                        <?php echo strtoupper(htmlspecialchars($ext, ENT_QUOTES, 'UTF-8')); ?> &middot; <?php echo $fileSizeKb; ?> KB
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($uploadedBy, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="<?php echo asset('api/download_newsletter.php'); ?>?id=<?php echo $nlId; ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[44px] text-xs bg-ibc-blue text-white rounded-lg hover:bg-ibc-blue-dark transition font-medium"
                   title="<?php echo htmlspecialchars($nl['original_filename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-download"></i>
                    <span class="hidden sm:inline">Herunterladen</span>
                </a>
                <?php if ($canManage): ?>
                <form method="POST" action="index.php"
                      data-confirm="Newsletter „<?php echo htmlspecialchars($nl['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" wirklich löschen?"
                      class="inline delete-form">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="newsletter_id" value="<?php echo $nlId; ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[44px] text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 transition font-medium">
                        <i class="fas fa-trash"></i>
                        <span class="hidden sm:inline">Löschen</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.delete-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var msg = this.dataset.confirm || 'Wirklich löschen?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
