<?php
require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

function shop_money(int $cents, string $currency = 'USD'): string
{
    return $currency . ' ' . number_format($cents / 100, 2);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_qty') {
        $product_id = trim($_POST['product_id'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 0);
        if ($product_id !== '' && $qty > 0) {
            foreach ($_SESSION['shop_cart'] as &$line) {
                if ($line['product_id'] === $product_id) {
                    $line['quantity'] = $qty;
                    log_action('cart_update', null, 'product', $product_id, ['qty' => $qty], $ip);
                    break;
                }
            }
            unset($line);
        }
        header('Location: cart.php');
        exit;
    } elseif ($action === 'remove_line') {
        $product_id = trim($_POST['product_id'] ?? '');
        $_SESSION['shop_cart'] = array_values(array_filter($_SESSION['shop_cart'], function ($line) use ($product_id) {
            return $line['product_id'] !== $product_id;
        }));
        if ($product_id !== '') {
            log_action('cart_remove', null, 'product', $product_id, [], $ip);
        }
        header('Location: cart.php');
        exit;
    } elseif ($action === 'clear_cart') {
        $_SESSION['shop_cart'] = [];
        log_action('cart_clear', null, null, null, [], $ip);
        header('Location: cart.php?msg=' . rawurlencode('Cart cleared.'));
        exit;
    }
}

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
$cart = $_SESSION['shop_cart'];
$cartCount = 0;
$cartSubtotal = 0;
foreach ($cart as $line) {
    $cartCount += (int)$line['quantity'];
    $cartSubtotal += ((int)$line['price_cents']) * ((int)$line['quantity']);
}

$shopName = get_setting('site_name', 'Shop');
$shopLogo = get_setting('site_logo_url', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        .toast { animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
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
                <a href="shop.php" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Continue Shopping</a>
                <a href="cart.php" class="relative p-2 text-gray-900" title="Cart">
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

<!-- Flash messages -->
<?php if ($message !== '' || $error !== ''): ?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
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

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Shopping Cart</h1>
        <?php if (!empty($cart)): ?>
        <form method="post">
            <input type="hidden" name="form_action" value="clear_cart">
            <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium transition-colors">Clear cart</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($cart)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <!-- Cart items -->
            <div class="divide-y divide-gray-100">
                <?php foreach ($cart as $line): ?>
                    <?php $lineTotal = ((int)$line['price_cents']) * ((int)$line['quantity']); ?>
                    <div class="p-4 sm:p-6 flex items-center gap-4 sm:gap-6">
                        <!-- Product info -->
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <?php echo htmlspecialchars(shop_money((int)$line['price_cents'], $line['currency']), ENT_QUOTES, 'UTF-8'); ?> each
                            </p>
                        </div>

                        <!-- Quantity -->
                        <form method="post" class="flex items-center gap-2">
                            <input type="hidden" name="form_action" value="update_qty">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($line['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="number" name="quantity" value="<?php echo (int)$line['quantity']; ?>" min="1"
                                   class="w-16 text-center border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                   onchange="this.form.submit()">
                        </form>

                        <!-- Line total -->
                        <div class="text-right">
                            <div class="text-base font-bold text-gray-900"><?php echo htmlspecialchars(shop_money($lineTotal, $line['currency']), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <!-- Remove -->
                        <form method="post">
                            <input type="hidden" name="form_action" value="remove_line">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($line['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-500 transition-colors" title="Remove item">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Subtotal -->
            <div class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <span class="text-base font-medium text-gray-700">Subtotal (<?php echo $cartCount; ?> item<?php echo $cartCount !== 1 ? 's' : ''; ?>)</span>
                    <span class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars(shop_money($cartSubtotal), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <a href="shop.php" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors flex items-center gap-1">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Continue Shopping
            </a>
            <a href="checkout.php"
               class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-8 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg">
                Proceed to Checkout
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>
        </div>

    <?php else: ?>
        <!-- Empty cart -->
        <div class="text-center py-20">
            <svg class="mx-auto h-20 w-20 text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h2>
            <p class="text-sm text-gray-500 mb-6">Looks like you haven't added anything to your cart yet.</p>
            <a href="shop.php"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg">
                Start Shopping
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>
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
document.querySelectorAll('.toast').forEach(function(el) {
    setTimeout(function() { el.remove(); }, 3000);
});
</script>
</body>
</html>
