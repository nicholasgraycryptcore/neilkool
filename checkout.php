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
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'checkout') {
        $cart = $_SESSION['shop_cart'];
        if (empty($cart)) {
            header('Location: checkout.php?err=' . rawurlencode('Cart is empty.'));
            exit;
        }

        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_contact = trim($_POST['customer_contact'] ?? '');

        $items = [];
        $subtotal = 0;
        foreach ($cart as $line) {
            $lineTotal = ((int)$line['price_cents']) * ((int)$line['quantity']);
            $subtotal += $lineTotal;
            $items[] = [
                'product_id' => $line['product_id'],
                'quantity' => (int)$line['quantity'],
                'unit_price_cents' => (int)$line['price_cents'],
            ];
        }

        try {
            $orderId = create_order([
                'source' => 'storefront',
                'status' => 'pending',
                'customer_name' => $customer_name !== '' ? $customer_name : null,
                'customer_contact' => $customer_contact !== '' ? $customer_contact : null,
            ], $items, true);

            log_action('storefront_checkout', $customer_contact ?: null, 'order', $orderId, ['total_cents' => $subtotal], $ip);
            $_SESSION['shop_cart'] = [];
            header('Location: checkout.php?msg=' . rawurlencode('Order placed successfully! Your reference: ' . $orderId));
            exit;
        } catch (Throwable $e) {
            header('Location: checkout.php?err=' . rawurlencode('Checkout failed: ' . $e->getMessage()));
            exit;
        }
    }
}

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
    <title>Checkout - <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        .toast { animation: slideIn 0.3s ease, fadeOut 0.3s ease 4.7s forwards; }
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
                <a href="cart.php" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors flex items-center gap-1">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Cart
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
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Checkout</h1>

    <?php if (empty($cart) && $message === ''): ?>
        <!-- Empty cart redirect -->
        <div class="text-center py-20">
            <svg class="mx-auto h-20 w-20 text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h2>
            <p class="text-sm text-gray-500 mb-6">Add some products before checking out.</p>
            <a href="shop.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg">
                Browse Products
            </a>
        </div>
    <?php elseif ($message !== ''): ?>
        <!-- Order success -->
        <div class="text-center py-20">
            <div class="mx-auto h-20 w-20 bg-emerald-100 rounded-full flex items-center justify-center mb-6">
                <svg class="h-10 w-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Thank you for your order!</h2>
            <p class="text-sm text-gray-500 mb-6"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="shop.php" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg">
                Continue Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="grid lg:grid-cols-5 gap-8">
            <!-- Checkout form -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">Contact Information</h2>
                    <form method="post" id="checkout-form" class="space-y-5">
                        <input type="hidden" name="form_action" value="checkout">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                            <input type="text" name="customer_name" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                   placeholder="Enter your full name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or Phone</label>
                            <input type="text" name="customer_contact" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                   placeholder="Enter your email or phone number">
                        </div>
                        <button type="submit"
                                class="w-full inline-flex justify-center items-center gap-2 px-6 py-3.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold transition-all hover:shadow-lg mt-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Place Order - <?php echo htmlspecialchars(shop_money($cartSubtotal), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order summary -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($cart as $line): ?>
                            <?php $lineTotal = ((int)$line['price_cents']) * ((int)$line['quantity']); ?>
                            <div class="py-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Qty: <?php echo (int)$line['quantity']; ?> x <?php echo htmlspecialchars(shop_money((int)$line['price_cents'], $line['currency']), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars(shop_money($lineTotal, $line['currency']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="border-t border-gray-200 mt-2 pt-4">
                        <div class="flex items-center justify-between">
                            <span class="text-base font-semibold text-gray-900">Total</span>
                            <span class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars(shop_money($cartSubtotal), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2"><?php echo $cartCount; ?> item<?php echo $cartCount !== 1 ? 's' : ''; ?> in your cart</p>
                    </div>
                    <a href="cart.php" class="block text-center text-sm text-gray-600 hover:text-gray-900 mt-4 transition-colors">Edit cart</a>
                </div>
            </div>
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
    setTimeout(function() { el.remove(); }, 5000);
});
</script>
</body>
</html>
