<?php
require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

$slug = $_GET['slug'] ?? '';
$product = $slug !== '' ? find_product_by_slug($slug) : null;

// Canonical URL for SEO
$canonicalUrl = 'product-' . ($product ? $product['slug'] : $slug);

if (!$product) {
    http_response_code(404);
    echo '<h1>Product not found</h1>';
    exit;
}

$categories = load_categories();
$categoryLookup = [];
foreach ($categories as $cat) {
    $categoryLookup[$cat['id']] = $cat['name'];
}

$primaryImage = product_primary_image_url($product);
$gallery = product_gallery_urls($product);
$categoryLabel = '';
if (!empty($product['category_id']) && isset($categoryLookup[$product['category_id']])) {
    $categoryLabel = $categoryLookup[$product['category_id']];
}
if (!empty($product['subcategory_id']) && isset($categoryLookup[$product['subcategory_id']])) {
    $categoryLabel = $categoryLabel !== '' ? $categoryLabel . ' / ' . $categoryLookup[$product['subcategory_id']] : $categoryLookup[$product['subcategory_id']];
}

// Try to load editable page content generated for this product
$pageSlug = 'product-' . $product['slug'];
$pageContent = '';
$pages = load_pages();
$page = find_page_by_slug($pages, $pageSlug);
if ($page) {
    $pageContent = render_content_with_products($page['content']);
}

function product_money_display(array $product): string
{
    return ($product['currency'] ?? 'USD') . ' ' . number_format(((int)$product['price_cents']) / 100, 2);
}

$cart = $_SESSION['shop_cart'];
$cartCount = 0;
foreach ($cart as $line) {
    $cartCount += (int)$line['quantity'];
}

$shopName = get_setting('site_name', 'Shop');
$shopLogo = get_setting('site_logo_url', '');
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
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
        .gallery-thumb { transition: all 0.2s ease; }
        .gallery-thumb:hover, .gallery-thumb.active { ring: 2px; box-shadow: 0 0 0 2px #111827; }
        .btn-cart { transition: all 0.2s ease; }
        .btn-cart:hover { transform: scale(1.02); }
        .btn-cart:active { transform: scale(0.98); }
        .toast { animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        .zoom-img { transition: transform 0.3s ease; cursor: zoom-in; }
        .zoom-img:hover { transform: scale(1.02); }
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
            <div class="flex items-center gap-4">
                <a href="shop.php" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">All Products</a>
                <a href="cart.php" class="relative p-2 text-gray-700 hover:text-gray-900 transition-colors" title="View cart">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Flash message -->
<?php if ($msg !== ''): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <div class="toast rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
        <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
    </div>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="shop.php" class="hover:text-gray-900 transition-colors">Shop</a>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <?php if ($categoryLabel !== ''): ?>
            <span class="hover:text-gray-900"><?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <?php endif; ?>
        <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </nav>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 flex-1 w-full">
    <div class="grid lg:grid-cols-2 gap-8 lg:gap-12">

        <!-- Image section -->
        <div class="space-y-4">
            <!-- Main image -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden aspect-square">
                <?php if ($primaryImage): ?>
                    <img id="main-image" src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="zoom-img w-full h-full object-contain p-4">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-gray-300 bg-gray-100">
                        <svg class="h-24 w-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gallery thumbnails -->
            <?php if (!empty($gallery)): ?>
                <div class="grid grid-cols-4 sm:grid-cols-5 gap-3">
                    <?php if ($primaryImage): ?>
                        <button type="button" onclick="switchImage(this, '<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>')"
                                class="gallery-thumb active aspect-square rounded-xl overflow-hidden border-2 border-gray-900 bg-white">
                            <img src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="Main" class="w-full h-full object-contain p-1">
                        </button>
                    <?php endif; ?>
                    <?php foreach ($gallery as $img): ?>
                        <button type="button" onclick="switchImage(this, '<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>')"
                                class="gallery-thumb aspect-square rounded-xl overflow-hidden border-2 border-transparent hover:border-gray-400 bg-white">
                            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="w-full h-full object-contain p-1">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product details -->
        <div class="flex flex-col">
            <!-- Category -->
            <?php if ($categoryLabel !== ''): ?>
                <span class="text-sm font-medium text-gray-400 uppercase tracking-wider mb-2">
                    <?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>

            <!-- Name -->
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>

            <!-- Price -->
            <div class="flex items-center gap-4 mb-6">
                <?php if ((int)($product['show_price'] ?? 1)): ?>
                <span class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars(product_money_display($product), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php else: ?>
                <span class="text-xl font-medium text-gray-500 italic">Contact for price</span>
                <?php endif; ?>
                <?php if ((int)$product['stock'] > 0): ?>
                    <span class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        In Stock (<?php echo (int)$product['stock']; ?>)
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center text-sm font-medium text-red-700 bg-red-50 px-3 py-1 rounded-full">Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if (!empty($product['description'])): ?>
                <div class="text-gray-600 leading-relaxed mb-8">
                    <?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?>
                </div>
            <?php endif; ?>

            <!-- SKU -->
            <?php if (!empty($product['sku'])): ?>
                <div class="text-sm text-gray-400 mb-6">SKU: <?php echo htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <!-- Add to cart -->
            <?php if ((int)$product['stock'] > 0): ?>
                <form method="post" action="shop.php" class="flex items-center gap-3 mb-8">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="form_action" value="add_to_cart">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars('product-' . $product['slug'] . '?msg=Added+to+cart', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="flex items-center border border-gray-300 rounded-xl overflow-hidden">
                        <button type="button" onclick="adjustQty(-1)" class="px-3 py-3 text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        </button>
                        <input type="number" id="qty-input" name="quantity" value="1" min="1" max="<?php echo (int)$product['stock']; ?>"
                               class="w-14 text-center border-x border-gray-300 py-3 text-sm font-medium focus:outline-none">
                        <button type="button" onclick="adjustQty(1)" class="px-3 py-3 text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-cart flex-1 inline-flex justify-center items-center gap-2 px-6 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <button disabled class="w-full py-3 rounded-xl bg-gray-100 text-gray-400 text-sm font-medium cursor-not-allowed mb-8">
                    Out of Stock
                </button>
            <?php endif; ?>

            <!-- Extra details -->
            <div class="border-t border-gray-200 pt-6 space-y-3">
                <div class="flex items-center gap-3 text-sm text-gray-600">
                    <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    Secure checkout
                </div>
                <div class="flex items-center gap-3 text-sm text-gray-600">
                    <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Fast delivery
                </div>
            </div>
        </div>
    </div>

    <!-- Page content 
    <?php if ($pageContent !== ''): ?>
        <div class="mt-12 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 prose max-w-none">
            <?php echo $pageContent; ?>
        </div>
    -->
    <?php endif; ?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-500">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.
    </div>
</footer>

<script>
function switchImage(thumb, url) {
    var mainImg = document.getElementById('main-image');
    if (mainImg) mainImg.src = url;
    document.querySelectorAll('.gallery-thumb').forEach(function(el) {
        el.classList.remove('active');
        el.style.borderColor = 'transparent';
    });
    thumb.classList.add('active');
    thumb.style.borderColor = '#111827';
}

function adjustQty(delta) {
    var input = document.getElementById('qty-input');
    if (!input) return;
    var val = parseInt(input.value) || 1;
    var min = parseInt(input.min) || 1;
    var max = parseInt(input.max) || 999;
    val = Math.max(min, Math.min(max, val + delta));
    input.value = val;
}

document.querySelectorAll('.toast').forEach(function(el) {
    setTimeout(function() { el.remove(); }, 3000);
});
</script>
</body>
</html>
