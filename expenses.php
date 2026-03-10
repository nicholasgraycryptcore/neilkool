<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$suppliers = load_suppliers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'save_expense') {
        $amount_raw = trim($_POST['amount'] ?? '0');
        $tax_raw = trim($_POST['tax'] ?? '0');
        $amount_cents = (int)round((float)$amount_raw * 100);
        $tax_cents = (int)round((float)$tax_raw * 100);
        if ($amount_cents <= 0) {
            header('Location: expenses.php?err=' . rawurlencode('Amount must be greater than zero.'));
            exit;
        }
        try {
            $id = save_expense([
                'id' => $_POST['id'] ?? null,
                'category' => trim($_POST['category'] ?? ''),
                'supplier_id' => trim($_POST['supplier_id'] ?? '') ?: null,
                'amount_cents' => $amount_cents,
                'tax_cents' => $tax_cents,
                'description' => trim($_POST['description'] ?? ''),
                'spent_at' => trim($_POST['spent_at'] ?? date('c')),
                'attachment_url' => trim($_POST['attachment_url'] ?? ''),
            ]);
            log_action('save_expense', current_user()['username'] ?? 'admin', 'expense', $id, ['amount_cents' => $amount_cents]);
            header('Location: expenses.php?msg=' . rawurlencode('Expense saved.'));
            exit;
        } catch (Throwable $e) {
            header('Location: expenses.php?err=' . rawurlencode('Could not save expense.'));
            exit;
        }
    } elseif ($action === 'delete_expense') {
        $id = $_POST['expense_id'] ?? '';
        if ($id === '') {
            header('Location: expenses.php?err=' . rawurlencode('Expense not found.'));
            exit;
        }
        delete_expense($id);
        log_action('delete_expense', current_user()['username'] ?? 'admin', 'expense', $id, []);
        header('Location: expenses.php?msg=' . rawurlencode('Expense deleted.'));
        exit;
    }
}

$expenses = load_expenses();
$editExpense = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    foreach ($expenses as $ex) {
        if ($ex['id'] === $editId) {
            $editExpense = $ex;
            break;
        }
    }
}
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $expenses = array_values(array_filter($expenses, function ($ex) use ($search) {
        return stripos($ex['category'] ?? '', $search) !== false || stripos($ex['description'] ?? '', $search) !== false;
    }));
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total = count($expenses);
$expensesPage = array_slice($expenses, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($total / $perPage));

$supplierLookup = [];
foreach ($suppliers as $s) {
    $supplierLookup[$s['id']] = $s['name'];
}

function fmt_money_exp(int $c): string {
    return '$' . number_format($c / 100, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Expenses</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="suppliers.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Suppliers</a>
            <a href="purchase_orders.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Purchase Orders</a>
            <a href="expenses.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Expenses</a>
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
        <h2 class="text-lg font-semibold text-slate-800"><?php echo $editExpense ? 'Edit expense' : 'Add expense'; ?></h2>
        <form method="post" class="grid md:grid-cols-2 gap-3">
            <input type="hidden" name="form_action" value="save_expense">
            <?php if ($editExpense): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editExpense['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <input type="text" name="category" class="w-full border rounded px-3 py-2 text-sm" placeholder="e.g., Office, Utilities" value="<?php echo $editExpense ? htmlspecialchars($editExpense['category'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Supplier (optional)</label>
                <select name="supplier_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">None</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $editExpense && $editExpense['supplier_id'] === $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                <input type="number" step="0.01" min="0" name="amount" class="w-full border rounded px-3 py-2 text-sm" required value="<?php echo $editExpense ? ((int)$editExpense['amount_cents'] / 100) : ''; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tax</label>
                <input type="number" step="0.01" min="0" name="tax" class="w-full border rounded px-3 py-2 text-sm" value="<?php echo $editExpense ? ((int)$editExpense['tax_cents'] / 100) : '0'; ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                <input type="date" name="spent_at" class="w-full border rounded px-3 py-2 text-sm" value="<?php echo htmlspecialchars($editExpense ? substr((string)$editExpense['spent_at'],0,10) : date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Attachment URL</label>
                <input type="url" name="attachment_url" class="w-full border rounded px-3 py-2 text-sm" placeholder="https://..." value="<?php echo $editExpense ? htmlspecialchars($editExpense['attachment_url'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full border rounded px-3 py-2 text-sm"><?php echo $editExpense ? htmlspecialchars($editExpense['description'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm"><?php echo $editExpense ? 'Update expense' : 'Save expense'; ?></button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Expenses</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search description/category" class="border rounded px-3 py-2 text-sm w-64">
                <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="expenses.php" class="text-sm text-blue-600">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <?php if (!empty($expensesPage)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Category</th>
                        <th class="py-2 pr-4">Supplier</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Tax</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2">Attachment</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expensesPage as $ex): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 text-slate-800"><?php echo htmlspecialchars($ex['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo !empty($ex['supplier_id']) && isset($supplierLookup[$ex['supplier_id']]) ? htmlspecialchars($supplierLookup[$ex['supplier_id']], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td class="py-2 pr-4 text-slate-800"><?php echo htmlspecialchars(fmt_money_exp((int)$ex['amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo htmlspecialchars(fmt_money_exp((int)$ex['tax_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-900 font-semibold"><?php echo htmlspecialchars(fmt_money_exp((int)$ex['total_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr((string)$ex['spent_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2">
                                <?php if (!empty($ex['attachment_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($ex['attachment_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-xs text-blue-600 hover:underline">Open</a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">—</span>
                                <?php endif; ?>
                                <div class="mt-1 space-x-1">
                                    <a href="expenses.php?edit=<?php echo htmlspecialchars($ex['id'], ENT_QUOTES, 'UTF-8'); ?>" class="text-xs text-blue-600 hover:underline">Edit</a>
                                    <form method="post" class="inline" onsubmit="return confirm('Delete this expense?');">
                                        <input type="hidden" name="form_action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?php echo htmlspecialchars($ex['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-700">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between mt-3 text-sm text-slate-700">
                    <div>Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
                    <div class="space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="expenses.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="expenses.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-600">No expenses recorded yet.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
