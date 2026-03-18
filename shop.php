<?php
require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple cart in session
if (!isset($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

function shop_money(int $cents, string $currency = 'USD'): string
{
    return $currency . ' ' . number_format($cents / 100, 2);
}

// Lightweight product fetcher for validation
function shop_find_product(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, price_cents, currency, stock, status FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Handle add-to-cart
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        header('Location: shop.php?err=' . rawurlencode('Invalid request. Please try again.'));
        exit;
    }
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_to_cart') {
        $product_id = trim($_POST['product_id'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 0);
        $return = trim($_POST['return'] ?? '');

        if ($product_id === '' || $qty <= 0) {
            header('Location: shop.php?err=' . rawurlencode('Choose a product and quantity.'));
            exit;
        }

        $product = shop_find_product($product_id);
        if (!$product || $product['status'] !== 'active') {
            header('Location: shop.php?err=' . rawurlencode('Product unavailable.'));
            exit;
        }
        if ($product['stock'] <= 0) {
            header('Location: shop.php?err=' . rawurlencode('Out of stock.'));
            exit;
        }

        // Merge if exists
        $added = false;
        foreach ($_SESSION['shop_cart'] as &$line) {
            if ($line['product_id'] === $product_id) {
                $line['quantity'] += $qty;
                $added = true;
                break;
            }
        }
        unset($line);
        if (!$added) {
            $_SESSION['shop_cart'][] = [
                'product_id' => $product_id,
                'name' => $product['name'],
                'price_cents' => (int)$product['price_cents'],
                'currency' => $product['currency'] ?? 'USD',
                'quantity' => $qty,
            ];
        }

        log_action('cart_add', null, 'product', $product_id, ['qty' => $qty], $ip);
        if ($return !== '' && strpos($return, '://') === false) {
            header('Location: ' . $return);
        } else {
            header('Location: shop.php?msg=' . rawurlencode('Added to cart.'));
        }
        exit;
    }
}

$categories = load_categories();
$categoryLookup = [];
foreach ($categories as $cat) {
    $categoryLookup[$cat['id']] = $cat['name'];
}

$selectedCategory = $_GET['category'] ?? null;
$searchQuery = trim($_GET['q'] ?? '');
$productFilters = ['status' => 'active'];
if ($selectedCategory) {
    $productFilters['category_id'] = $selectedCategory;
}
$products = load_products($productFilters);

// Filter by search
if ($searchQuery !== '') {
    $products = array_values(array_filter($products, function ($p) use ($searchQuery) {
        return stripos($p['name'], $searchQuery) !== false || stripos($p['description'] ?? '', $searchQuery) !== false;
    }));
}

$cart = $_SESSION['shop_cart'];
$cartCount = 0;
foreach ($cart as $line) {
    $cartCount += (int)$line['quantity'];
}

$shopName = get_setting('site_name', 'Shop');
$shopLogo = get_setting('site_logo_url', '');
$menuItems = load_shop_menu_items();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .product-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
        .product-img { transition: transform 0.3s ease; }
        .product-card:hover .product-img { transform: scale(1.05); }
        .btn-cart { transition: all 0.2s ease; }
        .btn-cart:hover { transform: scale(1.05); }
        .btn-cart:active { transform: scale(0.95); }
        .toast { animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        .badge-bounce { animation: bounce 0.3s ease; }
        @keyframes bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }
        .category-pill.active { background-color: #1e293b; color: #fff; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans flex flex-col">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="shop.php" class="flex items-center gap-2">
                    <?php if ($shopLogo !== ''): ?>
                        <img src="<?php echo htmlspecialchars($shopLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>" class="h-8 w-auto">
                    <?php endif; ?>
                    <span class="text-xl font-bold text-gray-900 tracking-tight"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>

            <!-- Navigation menu -->
            <?php if (!empty($menuItems)): ?>
            <nav class="hidden md:flex items-center gap-1">
                <?php foreach ($menuItems as $mi): ?>
                    <a href="<?php echo htmlspecialchars($mi['url'], ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo (int)$mi['open_new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                       class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100 transition-colors">
                        <?php echo htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <div class="flex items-center gap-4">
                <!-- Mobile menu toggle -->
                <?php if (!empty($menuItems)): ?>
                <button id="mobile-menu-btn" type="button" class="md:hidden p-2 text-gray-700 hover:text-gray-900 transition-colors" aria-label="Toggle menu">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <?php endif; ?>

                <!-- Cart Icon -->
                <a href="cart.php" class="relative p-2 text-gray-700 hover:text-gray-900 transition-colors" title="View cart">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    <?php if ($cartCount > 0): ?>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center badge-bounce">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile search -->
<div class="sm:hidden bg-white border-b border-gray-200 px-4 py-3">
    <form method="get" class="flex items-center">
        <?php if ($selectedCategory): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="relative w-full">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="Search products..."
                   class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-gray-50">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </form>
</div>

<!-- Mobile navigation menu -->
<?php if (!empty($menuItems)): ?>
<div id="mobile-menu" class="md:hidden bg-white border-b border-gray-200 hidden">
    <nav class="px-4 py-2 space-y-1">
        <?php foreach ($menuItems as $mi): ?>
            <a href="<?php echo htmlspecialchars($mi['url'], ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo (int)$mi['open_new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
               class="block px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100 transition-colors">
                <?php echo htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
<?php endif; ?>

<!-- Flash messages -->
<?php if ($message !== '' || $error !== ''): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <?php if ($message !== ''): ?>
        <div class="toast rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="toast rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

    <!-- Category filter pills -->
    <?php if (!empty($categories)): ?>
    <div class="flex flex-wrap items-center gap-2 mb-8">
        <a href="shop.php<?php echo $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : ''; ?>"
           class="category-pill inline-flex items-center px-4 py-2 rounded-full text-sm font-medium border border-gray-300 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-all <?php echo !$selectedCategory ? 'active' : 'bg-white text-gray-700'; ?>">
            All
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="shop.php?category=<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?><?php echo $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; ?>"
               class="category-pill inline-flex items-center px-4 py-2 rounded-full text-sm font-medium border border-gray-300 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-all <?php echo $selectedCategory === $cat['id'] ? 'active' : 'bg-white text-gray-700'; ?>">
                <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Search results info -->
    <?php if ($searchQuery !== ''): ?>
    <div class="mb-6 flex items-center gap-2 text-sm text-gray-600">
        <span>Showing results for "<strong><?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?></strong>"</span>
        <span class="text-gray-400">(<?php echo count($products); ?> found)</span>
        <a href="shop.php<?php echo $selectedCategory ? '?category=' . urlencode($selectedCategory) : ''; ?>" class="text-gray-900 underline hover:no-underline ml-1">Clear</a>
    </div>
    <?php endif; ?>

    <!-- Products grid -->
    <?php if (!empty($products)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($products as $prod): ?>
                <div class="product-card bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm flex flex-col">
                    <!-- Product image -->
                    <a href="product-<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="block overflow-hidden aspect-square bg-gray-100">
                        <?php if (!empty($prod['primary_image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($prod['primary_image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="product-img w-full h-full object-contain p-2">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </a>

                    <!-- Product info -->
                    <div class="p-4 flex flex-col flex-1">
                        <?php if ($prod['category_id'] && isset($categoryLookup[$prod['category_id']])): ?>
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">
                                <?php echo htmlspecialchars($categoryLookup[$prod['category_id']], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>

                        <a href="product-<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="text-base font-semibold text-gray-900 hover:text-gray-700 transition-colors line-clamp-2 mb-1">
                            <?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>

                        <?php if (!empty($prod['description'])): ?>
                            <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?php echo htmlspecialchars($prod['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php else: ?>
                            <div class="mb-3"></div>
                        <?php endif; ?>

                        <div class="mt-auto">
                            <div class="flex items-center justify-between mb-3">
                                <?php if ((int)($prod['show_price'] ?? 1)): ?>
                                <span class="text-lg font-bold text-gray-900">
                                    <?php echo htmlspecialchars(shop_money((int)$prod['price_cents'], $prod['currency']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-sm font-medium text-gray-500 italic">Contact for price</span>
                                <?php endif; ?>
                                <?php if ((int)$prod['stock'] > 0): ?>
                                    <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">In Stock</span>
                                <?php else: ?>
                                    <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded-full">Out of Stock</span>
                                <?php endif; ?>
                            </div>

                            <?php if ((int)$prod['stock'] > 0): ?>
                            <form method="post" class="flex items-center gap-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form_action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo (int)$prod['stock']; ?>"
                                       class="w-16 text-center border border-gray-300 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                                <button type="submit" class="btn-cart flex-1 inline-flex justify-center items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </form>
                            <?php else: ?>
                                <button disabled class="w-full py-2 rounded-lg bg-gray-100 text-gray-400 text-sm font-medium cursor-not-allowed">
                                    Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-20">
            <svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No products found</h3>
            <p class="text-sm text-gray-500">Try adjusting your search or filter to find what you're looking for.</p>
            <?php if ($searchQuery !== '' || $selectedCategory): ?>
                <a href="shop.php" class="inline-flex items-center mt-4 text-sm font-medium text-gray-900 hover:text-gray-700 underline">View all products</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-500">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.
    </div>
</footer>

<script>
// Auto-dismiss flash messages
document.querySelectorAll('.toast').forEach(function(el) {
    setTimeout(function() { el.remove(); }, 3000);
});

// Mobile menu toggle
(function() {
    var btn = document.getElementById('mobile-menu-btn');
    var menu = document.getElementById('mobile-menu');
    if (btn && menu) {
        btn.addEventListener('click', function() {
            menu.classList.toggle('hidden');
        });
    }
})();
</script>
</body>
</html>
