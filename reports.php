<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$products = load_products();
$lowStockThreshold = 5;
$lowStock = array_filter($products, function ($p) use ($lowStockThreshold) {
    return (int)$p['stock'] <= $lowStockThreshold;
});

$orders = load_purchase_orders();
$expenses = load_expenses();

// Totals
$revenueTotal = 0;
$db = get_db();
$stmt = $db->query('SELECT SUM(amount_cents) as total FROM payments');
$revenueTotal = (int)($stmt->fetchColumn() ?: 0);
$expenseTotal = 0;
foreach ($expenses as $ex) {
    $expenseTotal += (int)$ex['total_cents'];
}
$net = $revenueTotal - $expenseTotal;

function fmt_money($c) { return '$' . number_format($c / 100, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Reports</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="reports.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Reports</a>
            <a href="exports.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Exports</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <section class="grid md:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200 rounded shadow-sm p-4">
            <div class="text-xs uppercase text-slate-500">Revenue</div>
            <div class="text-2xl font-bold text-emerald-700"><?php echo htmlspecialchars(fmt_money($revenueTotal), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="bg-white border border-slate-200 rounded shadow-sm p-4">
            <div class="text-xs uppercase text-slate-500">Expenses</div>
            <div class="text-2xl font-bold text-rose-700"><?php echo htmlspecialchars(fmt_money($expenseTotal), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="bg-white border border-slate-200 rounded shadow-sm p-4">
            <div class="text-xs uppercase text-slate-500">Net</div>
            <div class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars(fmt_money($net), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Low stock products</h2>
            <span class="text-xs text-slate-500">Threshold: <?php echo $lowStockThreshold; ?></span>
        </div>
        <?php if (!empty($lowStock)): ?>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($lowStock as $p): ?>
                    <div class="border border-slate-200 rounded p-3 flex justify-between">
                        <div>
                            <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-xs text-slate-500">SKU: <?php echo htmlspecialchars($p['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="text-sm font-bold text-rose-700">Stock: <?php echo (int)$p['stock']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-600">No products below threshold.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-2">
        <h2 class="text-lg font-semibold text-slate-800">Recent purchase orders</h2>
        <?php if (!empty($orders)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">PO ID</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Ordered</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($orders, 0, 5) as $po): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-mono text-xs"><?php echo htmlspecialchars($po['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo htmlspecialchars($po['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-900"><?php echo htmlspecialchars(fmt_money((int)$po['total_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr((string)$po['ordered_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-600">No purchase orders yet.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-2">
        <h2 class="text-lg font-semibold text-slate-800">Recent expenses</h2>
        <?php if (!empty($expenses)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Category</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($expenses, 0, 5) as $ex): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 text-slate-800"><?php echo htmlspecialchars($ex['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-900"><?php echo htmlspecialchars(fmt_money((int)$ex['total_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr((string)$ex['spent_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-600">No expenses recorded yet.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
