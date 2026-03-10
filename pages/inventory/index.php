<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get sync results from session and clear them
$syncResult = $_SESSION['sync_result'] ?? null;
unset($_SESSION['sync_result']);

// Get search / filter parameters
$search = trim($_GET['search'] ?? '');

// Load inventory objects via Inventory model (includes SUM-based rental quantities)
$inventoryObjects = [];
$loadError = null;
try {
    $filters = [];
    if ($search !== '') {
        $filters['search'] = $search;
    }
    $inventoryObjects = Inventory::getAll($filters);
} catch (Exception $e) {
    $loadError = $e->getMessage();
    error_log('Inventory index: fetch failed: ' . $e->getMessage());
}

// Flash messages from checkout redirects
$checkoutSuccess = $_SESSION['checkout_success'] ?? null;
$checkoutError   = $_SESSION['checkout_error']   ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);


$title = 'Inventar - IBC Intranet';
ob_start();
?>

<div id="inventoryContent">
<?php if ($checkoutSuccess): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($checkoutSuccess); ?>
</div>
<?php endif; ?>

<?php if ($checkoutError): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($checkoutError); ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-2">
                <i class="fas fa-boxes text-purple-600 mr-3"></i>
                Inventar
            </h1>
            <p class="text-slate-600 dark:text-slate-400 text-lg"><?php echo count($inventoryObjects); ?> Artikel verfügbar</p>
        </div>
        <!-- Action Buttons -->
        <div class="flex gap-3 flex-wrap">
            <a href="my_rentals.php" class="bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-700 hover:border-purple-400 text-purple-700 dark:text-purple-300 px-5 py-3 rounded-xl flex items-center shadow-sm font-semibold transition-all hover:shadow-md">
                <i class="fas fa-clipboard-list mr-2"></i>Meine Ausleihen
            </a>
            <?php if (AuthHandler::isAdmin()): ?>
            <a href="sync.php" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-5 py-3 rounded-xl flex items-center shadow-lg font-semibold transition-all transform hover:scale-105">
                <i class="fas fa-sync-alt mr-2"></i> EasyVerein Sync
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sync Results -->
<?php if ($syncResult): ?>
<div class="mb-6 p-4 rounded-lg bg-blue-100 border border-blue-400 text-blue-700">
    <div class="flex items-start">
        <i class="fas fa-sync-alt mr-3 mt-1"></i>
        <div class="flex-1">
            <p class="font-semibold">EasyVerein Synchronisierung abgeschlossen</p>
            <ul class="mt-2 text-sm">
                <li>&#10003; Erstellt: <?php echo htmlspecialchars($syncResult['created']); ?> Artikel</li>
                <li>&#10003; Aktualisiert: <?php echo htmlspecialchars($syncResult['updated']); ?> Artikel</li>
                <li>&#10003; Archiviert: <?php echo htmlspecialchars($syncResult['archived']); ?> Artikel</li>
            </ul>
            <?php if (!empty($syncResult['errors'])): ?>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm underline">Fehler anzeigen (<?php echo count($syncResult['errors']); ?>)</summary>
                <ul class="mt-2 list-disc list-inside text-sm">
                    <?php foreach ($syncResult['errors'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Search Bar -->
<div class="card p-5 mb-8 shadow-lg border border-gray-200 dark:border-slate-700">
    <form method="GET" class="flex gap-3">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2 flex items-center">
                <i class="fas fa-search mr-2 text-purple-600"></i>Suche
            </label>
            <input
                type="text"
                name="search"
                placeholder="Artikelname oder Beschreibung..."
                value="<?php echo htmlspecialchars($search); ?>"
                class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
            >
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg transition-all transform hover:scale-105 shadow-md font-semibold">
                <i class="fas fa-search mr-2"></i>Suchen
            </button>
            <?php if ($search !== ''): ?>
            <a href="index.php" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- API Load Error -->
<?php if ($loadError): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    <strong>Fehler beim Laden der Inventardaten:</strong> <?php echo htmlspecialchars($loadError); ?>
</div>
<?php endif; ?>

<!-- ─── Inventory Grid (full-width) ─── -->
<div>
<div>

<!-- Inventory Grid -->
<?php if (empty($inventoryObjects) && !$loadError): ?>
<div class="card p-12 text-center">
    <i class="fas fa-inbox text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
    <p class="text-slate-900 dark:text-slate-100 text-lg">Keine Artikel gefunden</p>
    <?php if ($search !== ''): ?>
    <a href="index.php" class="mt-4 inline-block text-purple-600 hover:underline">Alle Artikel anzeigen</a>
    <?php elseif (AuthHandler::isAdmin()): ?>
    <a href="sync.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center mt-4">
        <i class="fas fa-sync-alt mr-2"></i> EasyVerein Sync
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($inventoryObjects as $item):
        $itemId        = $item['id'] ?? '';
        $itemName      = $item['name'] ?? '';
        $itemDesc      = $item['description'] ?? '';
        $itemPieces    = (int)($item['quantity'] ?? 0);
        $itemLoaned    = $itemPieces - (int)$item['available_quantity'];
        $itemAvailable = (int)$item['available_quantity'];
        $rawImage      = $item['image_path'] ?? null;
        if ($rawImage && strpos($rawImage, 'easyverein.com') !== false) {
            $imageSrc = '/api/easyverein_image.php?url=' . urlencode($rawImage);
        } elseif ($rawImage) {
            $imageSrc = '/' . ltrim($rawImage, '/');
        } else {
            $imageSrc = null;
        }
        $hasStock = $itemAvailable > 0;
    ?>
    <div class="group inventory-item-card bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-hidden border border-gray-100 dark:border-slate-700 flex flex-col">

        <!-- Image Area -->
        <div class="relative h-48 bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 dark:from-purple-900/30 dark:via-blue-900/30 dark:to-indigo-900/30 flex items-center justify-center overflow-hidden">
            <?php if ($imageSrc): ?>
            <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($itemName); ?>" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500" loading="lazy">
            <?php else: ?>
            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-200/20 to-blue-200/20 dark:from-purple-800/20 dark:to-blue-800/20 rounded-full blur-2xl"></div>
                <i class="fas fa-box-open text-gray-300 dark:text-gray-600 text-6xl relative z-10" aria-label="Kein Bild verfügbar"></i>
            </div>
            <?php endif; ?>

            <!-- Availability Badge (top-right) -->
            <div class="absolute top-3 right-3">
                <?php if ($hasStock): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-full bg-green-500 text-white shadow-lg">
                    <i class="fas fa-check-circle"></i><?php echo $itemAvailable; ?> verfügbar
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-full bg-red-500 text-white shadow-lg">
                    <i class="fas fa-times-circle"></i>Vergriffen
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card Content -->
        <div class="p-5 flex flex-col flex-1">
            <h3 class="font-bold text-slate-900 dark:text-white text-lg mb-1 line-clamp-2 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors" title="<?php echo htmlspecialchars($itemName); ?>">
                <?php echo htmlspecialchars($itemName); ?>
            </h3>

            <?php if (!empty($item['category_name'])): ?>
            <span class="inline-block self-start px-2 py-0.5 text-xs rounded-full mb-3 font-semibold" style="background-color: <?php echo htmlspecialchars($item['category_color'] ?? '#8b5cf6'); ?>20; color: <?php echo htmlspecialchars($item['category_color'] ?? '#8b5cf6'); ?>">
                <?php echo htmlspecialchars($item['category_name']); ?>
            </span>
            <?php endif; ?>

            <?php if ($itemDesc !== ''): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4 line-clamp-2 flex-1" title="<?php echo htmlspecialchars($itemDesc); ?>">
                <?php echo htmlspecialchars($itemDesc); ?>
            </p>
            <?php else: ?>
            <div class="flex-1"></div>
            <?php endif; ?>

            <!-- Stock Info -->
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="text-center px-2 py-2 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5">Gesamt</p>
                    <p class="font-bold text-slate-700 dark:text-slate-200 text-sm"><?php echo $itemPieces; ?></p>
                </div>
                <div class="text-center px-2 py-2 rounded-xl bg-orange-50 dark:bg-orange-900/20">
                    <p class="text-xs text-orange-400 dark:text-orange-500 mb-0.5">Ausgeliehen</p>
                    <p class="font-bold text-orange-600 dark:text-orange-400 text-sm"><?php echo $itemLoaned; ?></p>
                </div>
                <div class="text-center px-2 py-2 rounded-xl <?php echo $hasStock ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'; ?>">
                    <p class="text-xs <?php echo $hasStock ? 'text-green-500' : 'text-red-400'; ?> mb-0.5">Verfügbar</p>
                    <p class="font-bold <?php echo $hasStock ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?> text-sm"><?php echo $itemAvailable; ?></p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <a href="view.php?id=<?php echo htmlspecialchars($itemId); ?>"
                   class="flex items-center justify-center gap-1.5 px-3 py-2.5 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-semibold transition-all"
                   title="Details anzeigen">
                    <i class="fas fa-eye"></i>
                </a>
                <?php if ($hasStock): ?>
                <button
                    type="button"
                    id="cartBtn-<?php echo htmlspecialchars($itemId); ?>"
                    onclick="toggleCartItem(<?php echo htmlspecialchars(json_encode([
                        'id'       => (string)$itemId,
                        'name'     => $itemName,
                        'imageSrc' => $imageSrc ?? '',
                        'pieces'   => $itemAvailable,
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"
                    class="flex-1 py-2.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-xl font-bold text-sm transition-all transform hover:scale-[1.02] shadow-md hover:shadow-lg flex items-center justify-center gap-2"
                >
                    <i class="fas fa-cart-plus"></i>In den Warenkorb
                </button>
                <?php else: ?>
                <button
                    type="button"
                    disabled
                    class="flex-1 py-2.5 bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500 rounded-xl font-bold text-sm cursor-not-allowed flex items-center justify-center gap-2"
                >
                    <i class="fas fa-ban"></i>Vergriffen
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- /.inventory -->
</div><!-- /.main layout -->
</div><!-- /#inventoryContent -->

<!-- ─── Floating Cart Button ─── -->
<a href="checkout.php"
   id="cartFab"
   class="fixed top-24 right-8 z-50 w-14 h-14 rounded-full shadow-lg bg-ibc-blue flex items-center justify-center transition-all hover:scale-110 focus:outline-none focus:ring-4 focus:ring-purple-300"
   aria-label="Zum Warenkorb">
    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-16H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
    </svg>
    <span id="cartBadge"
          style="display:none"
          aria-live="polite"
          aria-atomic="true"
          class="absolute -top-2 -right-2 min-w-[1.4rem] h-[1.4rem] bg-red-500 text-white text-xs font-extrabold rounded-full flex items-center justify-center px-1 shadow-lg ring-2 ring-white">
        0
    </span>
</a>

<style>
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
@keyframes cart-pop {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.22); }
    70%  { transform: scale(0.94); }
    100% { transform: scale(1); }
}
.cart-pop { animation: cart-pop 0.35s cubic-bezier(0.36,0.07,0.19,0.97); }
@keyframes badge-pop {
    0%   { transform: scale(1); }
    30%  { transform: scale(1.55); }
    65%  { transform: scale(0.88); }
    100% { transform: scale(1); }
}
.badge-pop { animation: badge-pop 0.38s cubic-bezier(0.36,0.07,0.19,0.97); }
@keyframes btn-pulse {
    0%, 100% { box-shadow: 0 10px 25px rgba(0,102,179,0.35), 0 4px 10px rgba(0,79,140,0.2); }
    50%       { box-shadow: 0 14px 40px rgba(0,102,179,0.6), 0 6px 18px rgba(0,79,140,0.4); }
}
#cartFab.has-items { animation: btn-pulse 2s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) {
    .cart-pop, .badge-pop { animation: none; }
    #cartFab.has-items { animation: none !important; }
}
</style>

<script>
(function () {
    'use strict';

    var CART_KEY  = 'ibc_inventory_cart';
    var cart      = [];
    var csrfToken = <?php echo json_encode(CSRFHandler::getToken()); ?>;

    // ── Restore cart from localStorage on page load ──────────────────────────
    (function restoreCart() {
        try {
            var raw = localStorage.getItem(CART_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) { cart = parsed; }
            }
        } catch (e) {}
    }());

    // ── Session sync helper ──────────────────────────────────────────────────
    function syncSession(payload) {
        payload.csrf_token = csrfToken;
        fetch('/api/cart_toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).catch(function (err) { console.error('cart_toggle sync failed:', err); });
    }

    // ── Cart item toggle ─────────────────────────────────────────────────────
    window.toggleCartItem = function (item) {
        var idx = cart.findIndex(function (c) { return c.id === item.id; });
        if (idx === -1) {
            cart.push({ id: item.id, name: item.name, imageSrc: item.imageSrc || '', pieces: item.pieces, quantity: 1 });
            animateBadge();
        } else {
            cart.splice(idx, 1);
        }
        updateCartUI();
        updateCardButton(item.id);
        syncSession({ action: 'toggle', item_id: item.id, item_name: item.name, image_src: item.imageSrc || '', pieces: item.pieces, quantity: 1 });
    };

    window.clearCart = function () {
        var ids = cart.map(function (c) { return c.id; });
        cart = [];
        ids.forEach(updateCardButton);
        updateCartUI();
        syncSession({ action: 'clear' });
    };

    // ── Persist cart to localStorage and notify global badge ─────────────────
    function persistCart() {
        try {
            localStorage.setItem(CART_KEY, JSON.stringify(cart));
        } catch (e) {}
        window.dispatchEvent(new Event('ibc-inv-cart-updated'));
    }

    // ── UI helpers ───────────────────────────────────────────────────────────
    function updateCartUI() {
        var count = cart.length;
        var badge = document.getElementById('cartBadge');
        var fab   = document.getElementById('cartFab');

        if (badge) {
            badge.textContent   = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
        if (fab) fab.classList.toggle('has-items', count > 0);
        persistCart();
    }

    function updateCardButton(id) {
        var btn = document.getElementById('cartBtn-' + id);
        if (!btn) return;
        var inCart    = cart.some(function (c) { return c.id === id; });
        var baseClass = 'flex-1 py-2.5 text-white rounded-xl font-bold text-sm transition-all transform hover:scale-[1.02] shadow-md hover:shadow-lg flex items-center justify-center gap-2';
        if (inCart) {
            btn.className = baseClass + ' bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700';
            btn.innerHTML = '<i class="fas fa-check"></i>Im Warenkorb';
        } else {
            btn.className = baseClass + ' bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700';
            btn.innerHTML = '<i class="fas fa-cart-plus"></i>In den Warenkorb';
        }
    }

    function animateBadge() {
        var btn   = document.getElementById('cartFab');
        var badge = document.getElementById('cartBadge');
        if (!btn) return;
        btn.classList.remove('cart-pop');
        btn.offsetWidth; // reflow to restart animation
        btn.classList.add('cart-pop');
        if (badge) {
            badge.classList.remove('badge-pop');
            badge.offsetWidth;
            badge.classList.add('badge-pop');
        }
    }

    // Initial UI state
    updateCartUI();
    cart.forEach(function (item) { updateCardButton(item.id); });

}());
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
