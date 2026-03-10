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

$itemId   = $_GET['id'] ?? null;
$cartMode = !$itemId;          // true = full-cart checkout, false = single-item checkout

if (!$cartMode) {
    $item = Inventory::getById($itemId);
    if (!$item) {
        header('Location: index.php');
        exit;
    }
}

$message = '';
$error = '';

// Handle single-item checkout submission (cart mode uses JS / API)
if (!$cartMode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
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

$title = $cartMode
    ? 'Ausleih-Warenkorb - IBC Intranet'
    : 'Artikel ausleihen - ' . htmlspecialchars($item['name']);
ob_start();

if ($cartMode):
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CART CHECKOUT PAGE (no ?id= parameter)
     Items are read from localStorage (ibc_inventory_cart) via JavaScript.
     Submission is handled by fetch() to /api/inventory_request.php.
═══════════════════════════════════════════════════════════════════════════ -->

<!-- Back link -->
<div class="mb-6">
    <a href="index.php" class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 font-semibold group transition-all">
        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>Zurück zum Inventar
    </a>
</div>

<div class="max-w-2xl mx-auto" id="cartCheckoutPage">

    <!-- Page Header -->
    <h1 class="text-2xl sm:text-3xl font-extrabold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-6 flex items-center gap-3">
        <!-- Heroicons: shopping-cart -->
        <svg class="w-8 h-8 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        Ausleih-Warenkorb
    </h1>

    <!-- Status message -->
    <div id="checkoutMsg" class="hidden mb-4 rounded-xl px-4 py-3 text-sm font-medium"></div>

    <!-- Empty state (shown when cart is empty) -->
    <div id="checkoutCartEmpty" class="hidden bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 p-12 text-center">
        <div class="w-20 h-20 bg-purple-50 dark:bg-purple-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-purple-300 dark:text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <p class="text-slate-600 dark:text-slate-300 font-semibold text-lg mb-2">Ihr Warenkorb ist leer</p>
        <p class="text-slate-400 dark:text-slate-500 text-sm mb-6">Fügen Sie Artikel über das Inventar hinzu.</p>
        <a href="index.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02]">
            <i class="fas fa-boxes"></i>Zum Inventar
        </a>
    </div>

    <!-- Cart contents + form (shown when cart has items) -->
    <div id="checkoutCartContent" class="hidden space-y-6">

        <!-- Cart Items List -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-4 flex items-center gap-3">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shopping-cart text-white text-sm"></i>
                </div>
                <h2 class="text-base font-bold text-white">Ausgewählte Artikel</h2>
                <span id="checkoutItemCount" class="ml-auto text-purple-100 text-sm"></span>
            </div>
            <ul id="checkoutItemsList" class="divide-y divide-gray-100 dark:divide-slate-700"></ul>
        </div>

        <!-- Rental Details Form -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-100 dark:border-slate-700 overflow-hidden">
            <div class="bg-purple-50 dark:bg-purple-900/20 px-6 py-4 border-b border-purple-100 dark:border-purple-800">
                <h2 class="text-sm font-bold text-purple-700 dark:text-purple-300 uppercase tracking-wide flex items-center gap-2">
                    <i class="fas fa-calendar-alt"></i>Ausleihdetails (gelten für alle Artikel)
                </h2>
            </div>

            <div class="p-6 space-y-5">
                <!-- Date range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="checkoutStartDate" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fas fa-calendar text-purple-500 mr-1.5"></i>Von <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="checkoutStartDate"
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all">
                    </div>
                    <div>
                        <label for="checkoutEndDate" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fas fa-calendar text-purple-500 mr-1.5"></i>Bis <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="checkoutEndDate"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all">
                    </div>
                </div>

                <!-- Purpose -->
                <div>
                    <label for="checkoutPurpose" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        <i class="fas fa-tag text-purple-500 mr-1.5"></i>Verwendungszweck <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="checkoutPurpose"
                           placeholder="z.B. Veranstaltung, Projekt, Workshop"
                           maxlength="200"
                           class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 transition-all">
                </div>

                <!-- Info -->
                <div class="flex items-start gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl px-4 py-3">
                    <i class="fas fa-info-circle text-amber-500 mt-0.5 flex-shrink-0"></i>
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        Anfragen werden mit Status <strong>Ausstehend</strong> gespeichert und vom Vorstand geprüft.
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <a href="index.php"
                       class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-gray-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-all">
                        <i class="fas fa-arrow-left"></i>Zurück zum Inventar
                    </a>
                    <button type="button" id="checkoutSubmitBtn" onclick="submitCheckoutCart()"
                            class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 disabled:from-gray-400 disabled:to-gray-400 disabled:cursor-not-allowed text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02] disabled:scale-100">
                        <i class="fas fa-paper-plane"></i>
                        <span id="checkoutSubmitLabel">Alle ausleihen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var CART_KEY  = 'ibc_inventory_cart';
    var csrfToken = <?php echo json_encode(CSRFHandler::getToken()); ?>;
    var cart      = [];

    // ── Load cart from localStorage ──────────────────────────────────────────
    try {
        var raw = localStorage.getItem(CART_KEY);
        if (raw) {
            var parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) { cart = parsed; }
        }
    } catch (e) {}

    renderPage();

    function renderPage() {
        var emptyEl   = document.getElementById('checkoutCartEmpty');
        var contentEl = document.getElementById('checkoutCartContent');
        var countEl   = document.getElementById('checkoutItemCount');
        var listEl    = document.getElementById('checkoutItemsList');

        if (cart.length === 0) {
            if (emptyEl)   { emptyEl.classList.remove('hidden'); }
            if (contentEl) { contentEl.classList.add('hidden'); }
            return;
        }

        if (emptyEl)   { emptyEl.classList.add('hidden'); }
        if (contentEl) { contentEl.classList.remove('hidden'); }
        if (countEl)   { countEl.textContent = cart.length + ' Artikel'; }

        if (listEl) {
            listEl.innerHTML = cart.map(function (item) {
                var safeSrc = isSafeSrc(item.imageSrc) ? item.imageSrc : '';
                var thumb = safeSrc
                    ? '<img src="' + esc(safeSrc) + '" alt="' + esc(item.name) + '" class="w-full h-full object-contain" loading="lazy">'
                    : '<i class="fas fa-box-open text-gray-300 dark:text-gray-600 text-lg"></i>';

                return '<li class="flex items-center gap-4 px-5 py-4">'
                    + '<div class="w-14 h-14 flex-shrink-0 rounded-xl overflow-hidden bg-gradient-to-br from-purple-50 to-blue-50 dark:from-purple-900/30 dark:to-blue-900/30 border border-purple-100 dark:border-purple-800/50 flex items-center justify-center shadow-sm">'
                    + thumb
                    + '</div>'
                    + '<div class="flex-1 min-w-0">'
                    + '<p class="font-semibold text-slate-900 dark:text-white truncate">' + esc(item.name) + '</p>'
                    + '<p class="text-sm text-slate-500 dark:text-slate-400">Menge: <strong>' + esc(String(item.quantity)) + '</strong> von ' + esc(String(item.pieces)) + '</p>'
                    + '</div>'
                    + '<button onclick="removeItem(' + JSON.stringify(item.id) + ')" '
                    + 'class="flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" '
                    + 'aria-label="Entfernen"><i class="fas fa-trash-alt text-xs"></i></button>'
                    + '</li>';
            }).join('');
        }
    }

    window.removeItem = function (id) {
        cart = cart.filter(function (c) { return c.id !== id; });
        try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) {}
        window.dispatchEvent(new Event('ibc-inv-cart-updated'));
        renderPage();
    };

    window.submitCheckoutCart = function () {
        if (cart.length === 0) { return; }

        var startDate = document.getElementById('checkoutStartDate').value;
        var endDate   = document.getElementById('checkoutEndDate').value;
        var purpose   = (document.getElementById('checkoutPurpose').value || '').trim();

        if (!startDate || !endDate) {
            showMsg('Bitte Zeitraum auswählen.', 'error');
            return;
        }
        if (startDate > endDate) {
            showMsg('Startdatum muss vor dem Enddatum liegen.', 'error');
            return;
        }
        if (!purpose) {
            showMsg('Bitte Verwendungszweck angeben.', 'error');
            document.getElementById('checkoutPurpose').focus();
            return;
        }

        var btn = document.getElementById('checkoutSubmitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wird gesendet...';
        hideMsg();

        var promises = cart.map(function (item) {
            return fetch('/api/inventory_request.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    action:              'submit_request',
                    inventory_object_id: item.id,
                    start_date:          startDate,
                    end_date:            endDate,
                    quantity:            item.quantity,
                    purpose:             purpose,
                    csrf_token:          csrfToken
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { return { item: item, data: data }; })
            .catch(function (err) {
                console.error('Cart request failed for item ' + item.id + ':', err);
                return { item: item, data: { success: false, message: 'Netzwerkfehler' } };
            });
        });

        Promise.all(promises).then(function (results) {
            var failed = results.filter(function (r) { return !r.data.success; });
            if (failed.length === 0) {
                // Clear cart
                cart = [];
                try { localStorage.removeItem(CART_KEY); } catch (e) {}
                window.dispatchEvent(new Event('ibc-inv-cart-updated'));

                btn.innerHTML = '<i class="fas fa-check mr-2"></i>Gesendet!';
                showMsg('Alle Anfragen erfolgreich eingereicht! Du wirst weitergeleitet…', 'success');
                setTimeout(function () {
                    window.location.href = 'index.php';
                }, 2200);
            } else {
                var errDetails = failed.map(function (r) {
                    return r.item.name + (r.data.message ? ': ' + r.data.message : '');
                }).join('; ');
                showMsg('Fehler: ' + errDetails, 'error');
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i><span id="checkoutSubmitLabel">Erneut versuchen</span>';
            }
        });
    };

    function showMsg(text, type) {
        var el = document.getElementById('checkoutMsg');
        el.textContent = text;
        el.className = 'mb-4 rounded-xl px-4 py-3 text-sm font-medium ' +
            (type === 'success'
                ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700'
                : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700');
        el.classList.remove('hidden');
    }

    function hideMsg() {
        var el = document.getElementById('checkoutMsg');
        if (el) { el.classList.add('hidden'); }
    }

    function isSafeSrc(src) {
        return typeof src === 'string' && /^(https?:\/\/|\/).+/i.test(src);
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}());
</script>

<?php else: // ═══ SINGLE-ITEM CHECKOUT (existing flow, ?id= provided) ═══ ?>

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
<?php endif; // end single-item checkout ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
