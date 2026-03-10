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

// Handle cart actions and checkout
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Allow redirect back to the originating page if a relative path is provided
        if ($return !== '' && strpos($return, '://') === false) {
            header('Location: ' . $return);
        } else {
            header('Location: shop.php?msg=' . rawurlencode('Added to cart.'));
        }
        exit;
    } elseif ($action === 'update_qty') {
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
        header('Location: shop.php');
        exit;
    } elseif ($action === 'remove_line') {
        $product_id = trim($_POST['product_id'] ?? '');
        $_SESSION['shop_cart'] = array_values(array_filter($_SESSION['shop_cart'], function ($line) use ($product_id) {
            return $line['product_id'] !== $product_id;
        }));
        if ($product_id !== '') {
            log_action('cart_remove', null, 'product', $product_id, [], $ip);
        }
        header('Location: shop.php');
        exit;
    } elseif ($action === 'clear_cart') {
        $_SESSION['shop_cart'] = [];
        log_action('cart_clear', null, null, null, [], $ip);
        header('Location: shop.php?msg=' . rawurlencode('Cart cleared.'));
        exit;
    } elseif ($action === 'checkout') {
        $cart = $_SESSION['shop_cart'];
        if (empty($cart)) {
            header('Location: shop.php?err=' . rawurlencode('Cart is empty.'));
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
            header('Location: shop.php?msg=' . rawurlencode('Order placed! Reference: ' . $orderId));
            exit;
        } catch (Throwable $e) {
            header('Location: shop.php?err=' . rawurlencode('Checkout failed: ' . $e->getMessage()));
            exit;
        }
    }
}

$categories = load_categories();
$categoryLookup = [];
foreach ($categories as $cat) {
    $categoryLookup[$cat['id']] = $cat['name'];
}

$selectedCategory = $_GET['category'] ?? null;
$productFilters = ['status' => 'active'];
if ($selectedCategory) {
    $productFilters['category_id'] = $selectedCategory;
}
$products = load_products($productFilters);
$cart = $_SESSION['shop_cart'];

$cartSubtotal = 0;
foreach ($cart as $line) {
    $cartSubtotal += ((int)$line['price_cents']) * ((int)$line['quantity']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<header class="bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Shop</h1>
        <a href="shop.php" class="text-sm underline">Home</a>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Products</h2>
            <form method="get" class="flex items-center gap-2 text-sm">
                <label class="text-slate-700">Category</label>
                <select name="category" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedCategory === $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (!empty($products)): ?>
            <div class="grid gap-4 md:grid-cols-3">
                <?php foreach ($products as $prod): ?>
                    <div class="border border-slate-200 rounded p-3 flex flex-col gap-2">
                        <?php if (!empty($prod['primary_image_url'])): ?>
                            <a href="product.php?slug=<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars($prod['primary_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-40 object-cover rounded border border-slate-200">
                            </a>
                        <?php endif; ?>
                        <div>
                            <div class="text-base font-semibold text-slate-900">
                                <a href="product.php?slug=<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                            <div class="text-sm text-slate-600"><?php echo htmlspecialchars($prod['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="text-lg font-bold text-emerald-700"><?php echo htmlspecialchars(shop_money((int)$prod['price_cents'], $prod['currency']), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-xs text-slate-500">Stock: <?php echo (int)$prod['stock']; ?></div>
                        <form method="post" class="flex items-center gap-2">
                            <input type="hidden" name="form_action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="number" name="quantity" value="1" min="1" class="border rounded px-2 py-1 text-sm w-16">
                            <button type="submit" class="flex-1 inline-flex justify-center items-center px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Add</button>
                            <a href="product.php?slug=<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="text-xs text-slate-600 hover:text-slate-800">View</a>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-600">No products available.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Cart</h2>
            <form method="post">
                <input type="hidden" name="form_action" value="clear_cart">
                <button type="submit" class="text-sm text-rose-600 hover:text-rose-700">Clear cart</button>
            </form>
        </div>
        <?php if (!empty($cart)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Item</th>
                        <th class="py-2 pr-4">Qty</th>
                        <th class="py-2 pr-4">Price</th>
                        <th class="py-2 pr-4">Line</th>
                        <th class="py-2"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart as $line): ?>
                        <?php $lineTotal = ((int)$line['price_cents']) * ((int)$line['quantity']); ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4">
                                <form method="post" class="flex items-center gap-2">
                                    <input type="hidden" name="form_action" value="update_qty">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($line['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="number" name="quantity" value="<?php echo (int)$line['quantity']; ?>" min="1" class="border rounded px-2 py-1 text-sm w-20">
                                    <button type="submit" class="text-xs text-blue-600 hover:text-blue-700">Update</button>
                                </form>
                            </td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo htmlspecialchars(shop_money((int)$line['price_cents'], $line['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-900 font-semibold"><?php echo htmlspecialchars(shop_money($lineTotal, $line['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2">
                                <form method="post">
                                    <input type="hidden" name="form_action" value="remove_line">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($line['product_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-700">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-sm text-slate-700">Subtotal: <strong><?php echo htmlspecialchars(shop_money($cartSubtotal), ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php else: ?>
            <p class="text-sm text-slate-600">Cart is empty.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Checkout</h2>
        <form method="post" class="grid md:grid-cols-2 gap-3">
            <input type="hidden" name="form_action" value="checkout">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="customer_name" class="w-full border rounded px-3 py-2 text-sm" placeholder="Your name">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contact</label>
                <input type="text" name="customer_contact" class="w-full border rounded px-3 py-2 text-sm" placeholder="Email or phone">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm">Place order</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
