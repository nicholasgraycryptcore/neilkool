<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin', 'cashier']);
require __DIR__ . '/admin_nav.php';

if (!isset($_SESSION['pos_cart']) || !is_array($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
$posIp = $_SERVER['REMOTE_ADDR'] ?? null;

function format_money_pos(int $cents, string $currency = 'USD'): string
{
    return ($cents >= 0 ? '' : '-') . $currency . ' ' . number_format(abs($cents) / 100, 2);
}

// Helper: fetch a single product row by ID
function find_product_by_id(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, price_cents, currency, stock, primary_image_url FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_item') {
        $product_id = trim($_POST['product_id'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);

        if ($product_id === '' || $quantity <= 0) {
            header('Location: pos.php?err=' . rawurlencode('Select a product and enter a quantity greater than zero.'));
            exit;
        }

        $product = find_product_by_id($product_id);
        if (!$product) {
            header('Location: pos.php?err=' . rawurlencode('Product not found.'));
            exit;
        }

        $_SESSION['pos_cart'][] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price_cents' => (int)$product['price_cents'],
            'currency' => $product['currency'] ?? 'USD',
            'quantity' => $quantity,
            'primary_image_url' => $product['primary_image_url'] ?? null,
        ];
        header('Location: pos.php?msg=' . rawurlencode('Item added to cart.'));
        exit;
    } elseif ($action === 'remove_item') {
        $index = (int)($_POST['index'] ?? -1);
        if ($index >= 0 && isset($_SESSION['pos_cart'][$index])) {
            array_splice($_SESSION['pos_cart'], $index, 1);
        }
        header('Location: pos.php');
        exit;
    } elseif ($action === 'clear_cart') {
        $_SESSION['pos_cart'] = [];
        header('Location: pos.php?msg=' . rawurlencode('Cart cleared.'));
        exit;
    } elseif ($action === 'checkout') {
        $cart = $_SESSION['pos_cart'];
        if (empty($cart)) {
            header('Location: pos.php?err=' . rawurlencode('Cart is empty.'));
            exit;
        }

        $discount_raw = trim($_POST['discount'] ?? '0');
        $tax_rate_raw = trim($_POST['tax_rate'] ?? '0');
        $payment_amount_raw = trim($_POST['payment_amount'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');

        $subtotal = 0;
        $items = [];
        foreach ($cart as $line) {
            $lineTotal = (int)$line['price_cents'] * (int)$line['quantity'];
            $subtotal += $lineTotal;
            $items[] = [
                'product_id' => $line['product_id'],
                'quantity' => (int)$line['quantity'],
                'unit_price_cents' => (int)$line['price_cents'],
            ];
        }

        $discount_cents = (int)round((float)$discount_raw * 100);
        if ($discount_cents < 0) {
            $discount_cents = 0;
        }
        $tax_rate = (float)$tax_rate_raw;
        if ($tax_rate < 0) {
            $tax_rate = 0;
        }
        $tax_cents = (int)round($subtotal * ($tax_rate / 100));
        $total = $subtotal + $tax_cents - $discount_cents;
        if ($total < 0) {
            $total = 0;
        }

        $payment_amount_cents = $payment_amount_raw !== '' ? (int)round((float)$payment_amount_raw * 100) : $total;
        if ($payment_amount_cents <= 0) {
            header('Location: pos.php?err=' . rawurlencode('Payment amount must be greater than zero.'));
            exit;
        }

        try {
            $orderId = create_order([
                'source' => 'pos',
                'status' => 'pending',
                'tax_cents' => $tax_cents,
                'discount_cents' => $discount_cents,
            ], $items, true);

            record_payment($orderId, $payment_amount_cents, $payment_method, 'admin');
            log_action('pos_checkout', 'admin', 'order', $orderId, ['total_cents' => $total, 'payment_cents' => $payment_amount_cents], $posIp);
            send_order_notification($orderId);
            $_SESSION['pos_cart'] = [];
            header('Location: pos.php?msg=' . rawurlencode('Sale completed. Order ID: ' . $orderId));
            exit;
        } catch (Throwable $e) {
            header('Location: pos.php?err=' . rawurlencode('Checkout failed: ' . $e->getMessage()));
            exit;
        }
    }
}

$products = load_products(['status' => 'active']);
$cart = $_SESSION['pos_cart'];

// Precompute totals for display
$subtotal = 0;
foreach ($cart as $line) {
    $subtotal += ((int)$line['price_cents']) * ((int)$line['quantity']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashier</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<?php render_admin_sidebar('pos'); ?>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Add item</h2>
        <form method="post" class="grid md:grid-cols-4 gap-3 items-end">
            <input type="hidden" name="form_action" value="add_item">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Product</label>
                <select name="product_id" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?php echo htmlspecialchars($prod['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?>
                            — <?php echo htmlspecialchars(format_money_pos((int)$prod['price_cents'], $prod['currency']), ENT_QUOTES, 'UTF-8'); ?>
                            (Stock: <?php echo (int)$prod['stock']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
                <input type="number" name="quantity" value="1" min="1" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Add</button>
            </div>
        </form>
    </section>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4 space-y-4">
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
                        <th class="py-2 pr-4">Image</th>
                        <th class="py-2 pr-4">Qty</th>
                        <th class="py-2 pr-4">Price</th>
                        <th class="py-2 pr-4">Line total</th>
                        <th class="py-2"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart as $index => $line): ?>
                        <?php $lineTotal = ((int)$line['price_cents']) * ((int)$line['quantity']); ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4">
                                <?php if (!empty($line['primary_image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($line['primary_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Image" class="h-12 w-12 object-cover rounded border border-slate-200">
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 pr-4"><?php echo (int)$line['quantity']; ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo htmlspecialchars(format_money_pos((int)$line['price_cents'], $line['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-900 font-semibold"><?php echo htmlspecialchars(format_money_pos($lineTotal, $line['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2">
                                <form method="post">
                                    <input type="hidden" name="form_action" value="remove_item">
                                    <input type="hidden" name="index" value="<?php echo (int)$index; ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-700">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-600">Cart is empty.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Checkout</h2>
        <div class="text-sm text-slate-700">Subtotal: <strong><?php echo htmlspecialchars(format_money_pos($subtotal), ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <form method="post" class="grid md:grid-cols-4 gap-3 items-end">
            <input type="hidden" name="form_action" value="checkout">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Discount (amount)</label>
                <input type="number" step="0.01" min="0" name="discount" class="w-full border rounded px-3 py-2 text-sm" value="0">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tax rate (%)</label>
                <input type="number" step="0.01" min="0" name="tax_rate" class="w-full border rounded px-3 py-2 text-sm" value="0">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment amount</label>
                <input type="number" step="0.01" min="0" name="payment_amount" class="w-full border rounded px-3 py-2 text-sm" placeholder="Defaults to total">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Payment method</label>
                <select name="payment_method" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm">Complete sale</button>
            </div>
        </form>
    </section>
</main>
<?php render_admin_sidebar_close(); ?>
</body>
</html>
