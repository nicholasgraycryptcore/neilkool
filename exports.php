<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

function output_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    if ($type === 'expenses') {
        $filters = [];
        if ($from !== '') {
            $filters['from'] = $from;
        }
        if ($to !== '') {
            $filters['to'] = $to;
        }
        $expenses = load_expenses($filters);
        $rows = [];
        foreach ($expenses as $ex) {
            $rows[] = [
                $ex['id'],
                $ex['category'],
                $ex['supplier_id'],
                $ex['amount_cents'] / 100,
                $ex['tax_cents'] / 100,
                $ex['total_cents'] / 100,
                $ex['description'],
                $ex['spent_at'],
            ];
        }
        output_csv('expenses.csv', ['id','category','supplier_id','amount','tax','total','description','date'], $rows);
    } elseif ($type === 'purchase_orders') {
        $orders = load_purchase_orders();
        $rows = [];
        foreach ($orders as $po) {
            $rows[] = [
                $po['id'],
                $po['supplier_id'],
                $po['status'],
                $po['subtotal_cents'] / 100,
                $po['tax_cents'] / 100,
                $po['total_cents'] / 100,
                $po['notes'],
                $po['ordered_at'],
                $po['received_at'],
            ];
        }
        output_csv('purchase_orders.csv', ['id','supplier_id','status','subtotal','tax','total','notes','ordered_at','received_at'], $rows);
    } elseif ($type === 'accounting_detail') {
        $db = get_db();
        $rows = [];
        // Payments (revenue)
        $pQuery = 'SELECT p.id, p.order_id, p.amount_cents, p.received_at FROM payments p';
        $pParams = [];
        if ($from !== '' || $to !== '') {
            $clauses = [];
            if ($from !== '') {
                $clauses[] = 'p.received_at >= :fromp';
                $pParams[':fromp'] = $from;
            }
            if ($to !== '') {
                $clauses[] = 'p.received_at <= :top';
                $pParams[':top'] = $to;
            }
            $pQuery .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $stmt = $db->prepare($pQuery);
        $stmt->execute($pParams);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($payments as $p) {
            $rows[] = [
                $p['received_at'],
                'Revenue',
                $p['id'],
                'Payment for order ' . $p['order_id'],
                $p['amount_cents'] / 100,
            ];
        }
        // Expenses
        $filters = [];
        if ($from !== '') {
            $filters['from'] = $from;
        }
        if ($to !== '') {
            $filters['to'] = $to;
        }
        $expenses = load_expenses($filters);
        foreach ($expenses as $ex) {
            $rows[] = [
                $ex['spent_at'],
                'Expense',
                $ex['id'],
                $ex['description'] ?: $ex['category'],
                -1 * ($ex['total_cents'] / 100),
            ];
        }
        usort($rows, function($a,$b){ return strcmp($a[0], $b[0]); });
        output_csv('accounting_detail.csv', ['date','type','ref','description','amount'], $rows);
    } elseif ($type === 'accounting_summary') {
        $db = get_db();
        $payQuery = 'SELECT SUM(amount_cents) AS total FROM payments';
        $payParams = [];
        if ($from !== '' || $to !== '') {
            $clauses = [];
            if ($from !== '') {
                $clauses[] = 'received_at >= :fromp';
                $payParams[':fromp'] = $from;
            }
            if ($to !== '') {
                $clauses[] = 'received_at <= :top';
                $payParams[':top'] = $to;
            }
            $payQuery .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $stmt = $db->prepare($payQuery);
        $stmt->execute($payParams);
        $rev = (int)($stmt->fetchColumn() ?: 0);

        $expFilters = [];
        if ($from !== '') {
            $expFilters['from'] = $from;
        }
        if ($to !== '') {
            $expFilters['to'] = $to;
        }
        $expenses = load_expenses($expFilters);
        $expTotal = 0;
        foreach ($expenses as $ex) {
            $expTotal += (int)$ex['total_cents'];
        }
        $net = $rev - $expTotal;
        $rows = [
            ['Revenue', $rev / 100],
            ['Expenses', -1 * $expTotal / 100],
            ['Net Income', $net / 100],
        ];
        output_csv('income_statement.csv', ['line_item','amount'], $rows);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exports</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Exports</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="exports.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Exports</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Export expenses</h2>
        <form method="get" class="grid md:grid-cols-3 gap-2">
            <input type="hidden" name="export" value="expenses">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                <input type="date" name="from" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                <input type="date" name="to" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Download CSV</button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Export purchase orders</h2>
        <form method="get" class="flex items-center gap-2">
            <input type="hidden" name="export" value="purchase_orders">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-slate-700 hover:bg-slate-800 text-white text-sm">Download CSV</button>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Accounting detail (payments & expenses)</h2>
        <form method="get" class="grid md:grid-cols-3 gap-2">
            <input type="hidden" name="export" value="accounting_detail">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                <input type="date" name="from" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                <input type="date" name="to" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-slate-700 hover:bg-slate-800 text-white text-sm">Download CSV</button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Income statement summary</h2>
        <form method="get" class="grid md:grid-cols-3 gap-2">
            <input type="hidden" name="export" value="accounting_summary">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                <input type="date" name="from" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                <input type="date" name="to" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-sm">Download CSV</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
