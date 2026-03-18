<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);
require __DIR__ . '/admin_nav.php';

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

function order_money(int $cents): string
{
    return 'TTD ' . number_format($cents / 100, 2);
}

$statusColors = [
    'pending'   => 'bg-yellow-100 text-yellow-800',
    'payment'   => 'bg-orange-100 text-orange-800',
    'paid'      => 'bg-blue-100 text-blue-800',
    'delivered' => 'bg-indigo-100 text-indigo-800',
    'installed' => 'bg-purple-100 text-purple-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'cancelled' => 'bg-slate-100 text-slate-600',
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_status') {
        $oid = trim($_POST['order_id'] ?? '');
        $status = trim($_POST['status'] ?? '');
        if ($oid !== '' && isset(ORDER_STATUSES[$status])) {
            update_order_status($oid, $status);
            log_action('update_order_status', 'admin', 'order', $oid, ['status' => $status]);
            header('Location: orders.php?view=' . rawurlencode($oid) . '&msg=' . rawurlencode('Status updated to ' . ORDER_STATUSES[$status]));
            exit;
        }
        header('Location: orders.php?err=' . rawurlencode('Invalid status.'));
        exit;

    } elseif ($action === 'add_payment') {
        $oid = trim($_POST['order_id'] ?? '');
        $amount = (int)round((float)($_POST['amount'] ?? 0) * 100);
        $method = trim($_POST['method'] ?? 'cash');
        $note = trim($_POST['note'] ?? '');
        if ($oid !== '' && $amount > 0) {
            record_payment($oid, $amount, $method, 'admin', $note !== '' ? $note : null);
            log_action('add_payment', 'admin', 'order', $oid, ['amount_cents' => $amount, 'method' => $method]);
            header('Location: orders.php?view=' . rawurlencode($oid) . '&msg=' . rawurlencode('Payment recorded.'));
            exit;
        }
        header('Location: orders.php?view=' . rawurlencode($oid) . '&err=' . rawurlencode('Enter a valid amount.'));
        exit;

    } elseif ($action === 'save_notification_settings') {
        $enabled = isset($_POST['notifications_enabled']) ? '1' : '0';
        $emails = trim($_POST['notification_emails'] ?? '');
        set_setting('order_notifications_enabled', $enabled);
        set_setting('order_notification_emails', $emails);
        log_action('update_notification_settings', 'admin', 'settings', null, ['enabled' => $enabled]);
        header('Location: orders.php?msg=' . rawurlencode('Notification settings saved.'));
        exit;
    }
}

// View single order or order list
$viewId = $_GET['view'] ?? '';
$viewOrder = null;
$viewItems = [];
$viewPayments = [];
if ($viewId !== '') {
    $viewOrder = find_order($viewId);
    if ($viewOrder) {
        $viewItems = load_order_items($viewId);
        $viewPayments = load_order_payments($viewId);
    }
}

// Filters for list view
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');
$orders = load_orders(array_filter([
    'status' => $filterStatus,
    'search' => $filterSearch,
]));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalOrders = count($orders);
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$orders = array_slice($orders, ($page - 1) * $perPage, $perPage);

// Notification settings
$notificationsEnabled = are_order_notifications_enabled();
$notificationEmails = get_setting('order_notification_emails', 'neilkoolAC@gmail.com');

// Count by status for summary
$allOrders = load_orders();
$statusCounts = [];
foreach ($allOrders as $o) {
    $s = $o['status'];
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<?php render_admin_sidebar('orders'); ?>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

<?php if ($viewOrder): ?>
    <!-- ═══ SINGLE ORDER VIEW ═══ -->
    <div class="flex items-center gap-3 mb-2">
        <a href="orders.php" class="text-sm text-blue-600 hover:underline">&larr; All Orders</a>
    </div>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-5">
        <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Order <?php echo htmlspecialchars($viewOrder['id'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="text-sm text-slate-500 mt-1">
                    <?php echo htmlspecialchars($viewOrder['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                    &middot; Source: <span class="font-medium"><?php echo $viewOrder['source'] === 'storefront' ? 'Website' : 'POS'; ?></span>
                </p>
            </div>
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColors[$viewOrder['status']] ?? 'bg-slate-100 text-slate-600'; ?>">
                <?php echo htmlspecialchars(ORDER_STATUSES[$viewOrder['status']] ?? $viewOrder['status'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <!-- Customer info -->
        <div class="grid md:grid-cols-4 gap-4 mb-6 text-sm">
            <div class="bg-slate-50 rounded p-3">
                <div class="text-slate-500 text-xs font-medium mb-1">CUSTOMER</div>
                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($viewOrder['customer_name'] ?: 'Not provided', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="bg-slate-50 rounded p-3">
                <div class="text-slate-500 text-xs font-medium mb-1">EMAIL</div>
                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($viewOrder['customer_email'] ?: 'Not provided', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($viewOrder['customer_email'])): ?>
                    <div class="text-xs text-emerald-600 mt-1">Status updates will be sent here</div>
                <?php endif; ?>
            </div>
            <div class="bg-slate-50 rounded p-3">
                <div class="text-slate-500 text-xs font-medium mb-1">PHONE</div>
                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($viewOrder['customer_contact'] ?: 'Not provided', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="bg-slate-50 rounded p-3">
                <div class="text-slate-500 text-xs font-medium mb-1">TOTAL</div>
                <div class="font-bold text-slate-800 text-lg"><?php echo order_money((int)$viewOrder['total_cents']); ?></div>
            </div>
        </div>

        <!-- Update status -->
        <div class="flex items-center gap-3 mb-6 p-3 bg-slate-50 rounded">
            <span class="text-sm font-medium text-slate-700">Update Status:</span>
            <form method="post" class="flex items-center gap-2">
                <input type="hidden" name="form_action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($viewOrder['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <select name="status" class="border rounded px-3 py-1.5 text-sm">
                    <?php foreach (ORDER_STATUSES as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $viewOrder['status'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Update</button>
            </form>
        </div>

        <!-- Order items -->
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Items</h3>
        <div class="overflow-x-auto mb-6">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2 pr-4">Product</th>
                    <th class="py-2 pr-4 text-right">Unit Price</th>
                    <th class="py-2 pr-4 text-right">Qty</th>
                    <th class="py-2 text-right">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewItems as $item): ?>
                    <tr class="border-b last:border-0">
                        <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($item['product_name'] ?? $item['product_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="py-2 pr-4 text-right text-slate-600"><?php echo order_money((int)$item['unit_price_cents']); ?></td>
                        <td class="py-2 pr-4 text-right"><?php echo (int)$item['quantity']; ?></td>
                        <td class="py-2 text-right font-semibold"><?php echo order_money((int)$item['total_cents']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="border-t">
                    <td colspan="3" class="py-2 pr-4 text-right font-medium text-slate-600">Subtotal</td>
                    <td class="py-2 text-right font-semibold"><?php echo order_money((int)$viewOrder['subtotal_cents']); ?></td>
                </tr>
                <?php if ((int)$viewOrder['tax_cents'] > 0): ?>
                <tr>
                    <td colspan="3" class="py-1 pr-4 text-right text-slate-500">Tax</td>
                    <td class="py-1 text-right"><?php echo order_money((int)$viewOrder['tax_cents']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ((int)$viewOrder['discount_cents'] > 0): ?>
                <tr>
                    <td colspan="3" class="py-1 pr-4 text-right text-slate-500">Discount</td>
                    <td class="py-1 text-right text-red-600">-<?php echo order_money((int)$viewOrder['discount_cents']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-t-2">
                    <td colspan="3" class="py-2 pr-4 text-right font-bold text-slate-800">Total</td>
                    <td class="py-2 text-right font-bold text-lg"><?php echo order_money((int)$viewOrder['total_cents']); ?></td>
                </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payments -->
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Payments</h3>
        <?php
        $paidTotal = (int)($viewOrder['paid_cents'] ?? 0);
        $owing = (int)$viewOrder['total_cents'] - $paidTotal;
        ?>
        <div class="flex gap-4 mb-3 text-sm">
            <span class="text-slate-500">Paid: <strong class="text-emerald-700"><?php echo order_money($paidTotal); ?></strong></span>
            <?php if ($owing > 0): ?>
                <span class="text-slate-500">Owing: <strong class="text-red-600"><?php echo order_money($owing); ?></strong></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($viewPayments)): ?>
            <div class="overflow-x-auto mb-4">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Method</th>
                        <th class="py-2 pr-4 text-right">Amount</th>
                        <th class="py-2 pr-4">By</th>
                        <th class="py-2">Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($viewPayments as $pay): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr($pay['received_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 capitalize"><?php echo htmlspecialchars($pay['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-right font-semibold"><?php echo order_money((int)$pay['amount_cents']); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars($pay['received_by'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 text-slate-500"><?php echo htmlspecialchars($pay['note'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-500 mb-4">No payments recorded.</p>
        <?php endif; ?>

        <!-- Record payment -->
        <?php if ($owing > 0 && $viewOrder['status'] !== 'cancelled'): ?>
        <form method="post" class="flex flex-wrap items-end gap-3 p-3 bg-slate-50 rounded">
            <input type="hidden" name="form_action" value="add_payment">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($viewOrder['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="<?php echo number_format($owing / 100, 2, '.', ''); ?>" class="border rounded px-3 py-1.5 text-sm w-32" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Method</label>
                <select name="method" class="border rounded px-3 py-1.5 text-sm">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="transfer">Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Note</label>
                <input type="text" name="note" class="border rounded px-3 py-1.5 text-sm w-48" placeholder="Optional">
            </div>
            <button type="submit" class="px-4 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">Record Payment</button>
        </form>
        <?php endif; ?>
    </section>

<?php else: ?>
    <!-- ═══ ORDER LIST VIEW ═══ -->
    <h1 class="text-xl font-bold text-slate-800">Orders</h1>

    <!-- Status summary cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        <?php foreach (ORDER_STATUSES as $key => $label): ?>
            <?php $cnt = $statusCounts[$key] ?? 0; ?>
            <a href="orders.php?status=<?php echo $key; ?>" class="block bg-white rounded border border-slate-200 p-3 text-center hover:shadow transition-shadow <?php echo $filterStatus === $key ? 'ring-2 ring-blue-500' : ''; ?>">
                <div class="text-2xl font-bold text-slate-800"><?php echo $cnt; ?></div>
                <div class="text-xs font-medium text-slate-500"><?php echo $label; ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & filter -->
    <div class="flex flex-wrap items-center gap-3">
        <form method="get" class="flex items-center gap-2">
            <?php if ($filterStatus !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
            <input type="text" name="q" value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by ID, name, or contact" class="border rounded px-3 py-2 text-sm w-72">
            <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
        </form>
        <?php if ($filterStatus !== '' || $filterSearch !== ''): ?>
            <a href="orders.php" class="text-sm text-blue-600 hover:underline">Clear filters</a>
        <?php endif; ?>
    </div>

    <!-- Orders table -->
    <section class="bg-white rounded shadow-sm border border-slate-200 overflow-hidden">
        <?php if (!empty($orders)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="text-left text-slate-500 border-b bg-slate-50">
                    <th class="py-3 px-4">Order ID</th>
                    <th class="py-3 px-4">Customer</th>
                    <th class="py-3 px-4">Source</th>
                    <th class="py-3 px-4 text-right">Total</th>
                    <th class="py-3 px-4 text-right">Paid</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $ord): ?>
                    <?php $paid = (int)($ord['paid_cents'] ?? 0); ?>
                    <tr class="border-b last:border-0 hover:bg-slate-50">
                        <td class="py-3 px-4 font-mono text-xs text-slate-600"><?php echo htmlspecialchars(substr($ord['id'], 0, 20), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="py-3 px-4">
                            <div class="font-medium text-slate-800"><?php echo htmlspecialchars($ord['customer_name'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($ord['customer_contact']): ?>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($ord['customer_contact'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-slate-600"><?php echo $ord['source'] === 'storefront' ? 'Web' : 'POS'; ?></td>
                        <td class="py-3 px-4 text-right font-semibold"><?php echo order_money((int)$ord['total_cents']); ?></td>
                        <td class="py-3 px-4 text-right <?php echo $paid >= (int)$ord['total_cents'] ? 'text-emerald-600' : 'text-orange-600'; ?>"><?php echo order_money($paid); ?></td>
                        <td class="py-3 px-4">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $statusColors[$ord['status']] ?? 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo htmlspecialchars(ORDER_STATUSES[$ord['status']] ?? $ord['status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-slate-500 text-xs"><?php echo htmlspecialchars(substr($ord['created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="py-3 px-4">
                            <a href="orders.php?view=<?php echo rawurlencode($ord['id']); ?>" class="text-xs text-blue-600 hover:underline font-medium">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t text-sm text-slate-600">
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalOrders; ?> orders)</span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="orders.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filterStatus); ?>&q=<?php echo urlencode($filterSearch); ?>" class="px-3 py-1 rounded border">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="orders.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filterStatus); ?>&q=<?php echo urlencode($filterSearch); ?>" class="px-3 py-1 rounded border">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-12 text-sm text-slate-500">
                <p class="mb-1">No orders found.</p>
                <?php if ($filterStatus !== '' || $filterSearch !== ''): ?>
                    <a href="orders.php" class="text-blue-600 hover:underline">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ═══ NOTIFICATION SETTINGS ═══ -->
    <section class="bg-white rounded shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Order Notifications</h2>
        <p class="text-sm text-slate-500 mb-4">Get email alerts when new orders are placed from the shop or POS.</p>
        <form method="post" class="space-y-4">
            <input type="hidden" name="form_action" value="save_notification_settings">
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="notifications_enabled" value="1" class="sr-only peer" <?php echo $notificationsEnabled ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-sm font-medium text-slate-700">Enable email notifications</span>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notification emails</label>
                <input type="text" name="notification_emails" value="<?php echo htmlspecialchars($notificationEmails, ENT_QUOTES, 'UTF-8'); ?>" class="w-full border rounded px-3 py-2 text-sm" placeholder="email1@example.com, email2@example.com">
                <p class="text-xs text-slate-500 mt-1">Comma-separated. All listed emails will receive order alerts.</p>
            </div>
            <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save Settings</button>
        </form>
    </section>
<?php endif; ?>

</main>
</body>
<?php render_admin_sidebar_close(); ?>
</html>
