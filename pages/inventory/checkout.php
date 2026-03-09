<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';
require_once __DIR__ . '/../../includes/services/MicrosoftGraphService.php';
require_once __DIR__ . '/../../src/MailService.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$itemId = $_GET['id'] ?? null;
if (!$itemId) {
    header('Location: index.php');
    exit;
}

$item = Inventory::getById($itemId);
if (!$item) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');

    // Determine where to redirect after checkout; constrain to a known safe value.
    $returnTo = ($_POST['return_to'] ?? '') === 'index' ? 'index' : 'view';
    
    $quantity = intval($_POST['quantity'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '') ?: null;
    $expectedReturn = trim($_POST['expected_return_at'] ?? $_POST['expected_return'] ?? '') ?: null;
    
    if ($quantity <= 0) {
        $error = 'Bitte geben Sie eine gültige Menge ein';
        if ($returnTo === 'index') {
            $_SESSION['checkout_error'] = $error;
            header('Location: index.php');
            exit;
        }
    } else {
        $result = Inventory::checkoutItem($itemId, $_SESSION['user_id'], $quantity, $purpose, $destination, $expectedReturn, $startDate);
        
        if ($result['success']) {
            // Send notification email to board
            $borrowerEmail = $_SESSION['user_email'] ?? 'Unbekannt';
            $safeSubject = str_replace(["\r", "\n"], '', $item['name']);
            $startDateRow = $startDate && strtotime($startDate) !== false
                ? '<tr><td>Startdatum</td><td>' . htmlspecialchars(date('d.m.Y', strtotime($startDate))) . '</td></tr>'
                : '';
            $returnRow = $expectedReturn && strtotime($expectedReturn) !== false
                ? '<tr><td>Rückgabe bis</td><td>' . htmlspecialchars(date('d.m.Y', strtotime($expectedReturn))) . '</td></tr>'
                : '';
            $emailBody = MailService::getTemplate(
                'Neue Ausleihe im Inventar',
                '<p class="email-text">Ein Mitglied hat einen Artikel aus dem Inventar ausgeliehen.</p>
                <table class="info-table">
                    <tr><td>Artikel</td><td>' . htmlspecialchars($item['name']) . '</td></tr>
                    <tr><td>Menge</td><td>' . htmlspecialchars($quantity . ' ' . ($item['unit'] ?? 'Stück')) . '</td></tr>
                    <tr><td>Ausgeliehen von</td><td>' . htmlspecialchars($borrowerEmail) . '</td></tr>
                    <tr><td>Verwendungszweck</td><td>' . htmlspecialchars($purpose) . '</td></tr>
                    <tr><td>Zielort</td><td>' . htmlspecialchars($destination ?: '-') . '</td></tr>
                    ' . $startDateRow . '
                    ' . $returnRow . '
                    <tr><td>Datum</td><td>' . date('d.m.Y H:i') . '</td></tr>
                </table>'
            );
            MailService::sendEmail(MAIL_INVENTORY, 'Neue Ausleihe: ' . $safeSubject, $emailBody);

            $_SESSION['checkout_success'] = $result['message'];
            if ($returnTo === 'index') {
                header('Location: index.php');
            } else {
                header('Location: view.php?id=' . $itemId);
            }
            exit;
        } else {
            $error = $result['message'];
            if ($returnTo === 'index') {
                $_SESSION['checkout_error'] = $error;
                header('Location: index.php');
                exit;
            }
        }
    }
}

$title = 'Artikel ausleihen - ' . htmlspecialchars($item['name']);
ob_start();
?>

<!-- Back link -->
<div class="mb-6">
    <a href="view.php?id=<?php echo $item['id']; ?>" class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 font-semibold group transition-all">
        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>Zurück zum Artikel
    </a>
</div>

<?php if ($error): ?>
<div class="mb-6 flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-300 rounded-2xl shadow-sm">
    <i class="fas fa-exclamation-circle text-red-500 text-lg flex-shrink-0"></i>
    <span><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<div class="max-w-2xl mx-auto">

    <!-- Progress Steps -->
    <div class="flex items-center justify-center gap-0 mb-8">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 text-white flex items-center justify-center text-sm font-bold shadow">1</div>
            <span class="text-sm font-semibold text-purple-700 dark:text-purple-300">Artikel wählen</span>
        </div>
        <div class="flex-1 h-0.5 bg-gradient-to-r from-purple-400 to-blue-400 mx-3 max-w-[3rem]"></div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 text-white flex items-center justify-center text-sm font-bold shadow ring-4 ring-purple-200 dark:ring-purple-800">2</div>
            <span class="text-sm font-semibold text-purple-700 dark:text-purple-300">Details</span>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200 dark:bg-slate-700 mx-3 max-w-[3rem]"></div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500 flex items-center justify-center text-sm font-bold">3</div>
            <span class="text-sm text-gray-400 dark:text-slate-500">Bestätigung</span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden">

        <!-- Card Header -->
        <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-5">
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-hand-holding-box"></i>
                Artikel ausleihen
            </h1>
        </div>

        <!-- Item Info Banner -->
        <div class="flex items-center justify-between gap-4 px-6 py-4 bg-purple-50 dark:bg-purple-900/20 border-b border-purple-100 dark:border-purple-800">
            <div class="min-w-0">
                <p class="text-xs text-purple-600 dark:text-purple-400 font-semibold uppercase tracking-wide mb-0.5">Ausgewählter Artikel</p>
                <h2 class="font-bold text-slate-900 dark:text-white text-base truncate"><?php echo htmlspecialchars($item['name']); ?></h2>
                <?php if ($item['category_name']): ?>
                <span class="inline-block px-2 py-0.5 text-xs rounded-full mt-1 font-medium" style="background-color: <?php echo htmlspecialchars($item['category_color']); ?>20; color: <?php echo htmlspecialchars($item['category_color']); ?>">
                    <?php echo htmlspecialchars($item['category_name']); ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-xs text-purple-600 dark:text-purple-400 font-semibold uppercase tracking-wide mb-0.5">Verfügbar</p>
                <p class="text-2xl font-extrabold <?php echo $item['available_quantity'] <= $item['min_stock'] && $item['min_stock'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'; ?>">
                    <?php echo $item['available_quantity']; ?> <span class="text-base font-semibold"><?php echo htmlspecialchars($item['unit']); ?></span>
                </p>
            </div>
        </div>

        <!-- Checkout Form -->
        <form method="POST" id="checkout-rental-form" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
            <input type="hidden" name="checkout" value="1">

            <!-- Quantity -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    <i class="fas fa-cubes text-purple-500 mr-1.5"></i>Menge <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    name="quantity"
                    min="1"
                    max="<?php echo $item['available_quantity']; ?>"
                    required
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
                    placeholder="Anzahl der auszuleihenden Artikel"
                >
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                    Maximal verfügbar: <strong><?php echo $item['available_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></strong>
                </p>
            </div>

            <!-- Purpose -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    <i class="fas fa-tag text-purple-500 mr-1.5"></i>Verwendungszweck <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    name="purpose"
                    required
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
                    placeholder="z.B. Veranstaltung, Projekt, Workshop"
                >
            </div>

            <!-- Destination -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    <i class="fas fa-map-marker-alt text-purple-500 mr-1.5"></i>Zielort / Verwendungsort
                    <span class="ml-1 text-xs text-slate-400 font-normal">(optional)</span>
                </label>
                <input
                    type="text"
                    name="destination"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
                    placeholder="z.B. Konferenzraum A, Offsite-Event"
                >
            </div>

            <!-- Info note -->
            <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl px-4 py-3">
                <i class="fas fa-info-circle text-blue-500 mt-0.5 flex-shrink-0"></i>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Der Bestand wird sofort reduziert. Bitte nach der Verwendung zurückgeben.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <a href="view.php?id=<?php echo $item['id']; ?>"
                   class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-all">
                    <i class="fas fa-times"></i>Abbrechen
                </a>
                <button type="submit" id="checkout-rental-btn"
                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02]">
                    <i class="fas fa-check"></i>Ausleihen bestätigen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('checkout-rental-form');
    var btn  = document.getElementById('checkout-rental-btn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wird gesendet...';
        });
    }
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
