<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$suppliers = load_suppliers();
$products = load_products();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'save_po') {
        $supplier_id = trim($_POST['supplier_id'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $items = [];
        $product_ids = $_POST['item_product_id'] ?? [];
        $descs = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_quantity'] ?? [];
        $costs = $_POST['item_unit_cost'] ?? [];
        foreach ($qtys as $idx => $qtyRaw) {
            $qty = (int)$qtyRaw;
            $costCents = (int)round(((float)($costs[$idx] ?? 0)) * 100);
            $desc = trim($descs[$idx] ?? '');
            $pid = trim($product_ids[$idx] ?? '');
            if ($qty <= 0 || $costCents < 0) {
                continue;
            }
            if ($desc === '' && $pid === '') {
                continue;
            }
            $items[] = [
                'product_id' => $pid !== '' ? $pid : null,
                'description' => $desc,
                'quantity' => $qty,
                'unit_cost_cents' => $costCents,
            ];
        }
        if (empty($items)) {
            header('Location: purchase_orders.php?err=' . rawurlencode('Add at least one item.'));
            exit;
        }
        try {
            $poId = $_POST['id'] ?? null;
            if ($poId) {
                update_purchase_order($poId, [
                    'supplier_id' => $supplier_id !== '' ? $supplier_id : null,
                    'status' => 'sent',
                    'notes' => $notes,
                    'tax_cents' => 0,
                ], $items);
                log_action('update_po', current_user()['username'] ?? 'admin', 'purchase_order', $poId, ['items' => count($items)]);
                header('Location: purchase_orders.php?msg=' . rawurlencode('Purchase order updated.'));
            } else {
                $poId = create_purchase_order([
                    'supplier_id' => $supplier_id !== '' ? $supplier_id : null,
                    'status' => 'sent',
                    'notes' => $notes,
                    'tax_cents' => 0,
                    'ordered_at' => date('c'),
                ], $items);
                log_action('create_po', current_user()['username'] ?? 'admin', 'purchase_order', $poId, ['items' => count($items)]);
                header('Location: purchase_orders.php?msg=' . rawurlencode('Purchase order created.'));
            }
            exit;
        } catch (Throwable $e) {
            header('Location: purchase_orders.php?err=' . rawurlencode('Could not create PO.'));
            exit;
        }
    } elseif ($action === 'receive_po') {
        $id = $_POST['po_id'] ?? '';
        if ($id === '') {
            header('Location: purchase_orders.php?err=' . rawurlencode('PO not found.'));
            exit;
        }
        try {
            receive_purchase_order($id, current_user()['username'] ?? 'admin');
            header('Location: purchase_orders.php?msg=' . rawurlencode('PO received and inventory updated.'));
            exit;
        } catch (Throwable $e) {
            header('Location: purchase_orders.php?err=' . rawurlencode('Could not receive PO: ' . $e->getMessage()));
            exit;
        }
    }
    elseif ($action === 'delete_po') {
        $id = $_POST['po_id'] ?? '';
        if ($id === '') {
            header('Location: purchase_orders.php?err=' . rawurlencode('PO not found.'));
            exit;
        }
        $po = find_purchase_order($id);
        if (!$po) {
            header('Location: purchase_orders.php?err=' . rawurlencode('PO not found.'));
            exit;
        }
        if ($po['status'] === 'received') {
            header('Location: purchase_orders.php?err=' . rawurlencode('Cannot delete a received PO.'));
            exit;
        }
        delete_purchase_order($id);
        log_action('delete_po', current_user()['username'] ?? 'admin', 'purchase_order', $id, []);
        header('Location: purchase_orders.php?msg=' . rawurlencode('Purchase order deleted.'));
        exit;
    }
}

$poList = load_purchase_orders();
$editPo = null;
$editItems = [];
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editPo = find_purchase_order($editId);
    if ($editPo) {
        $editItems = load_purchase_order_items($editId);
    }
}
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $poList = array_values(array_filter($poList, function ($po) use ($search) {
        return stripos($po['id'], $search) !== false || stripos($po['notes'] ?? '', $search) !== false;
    }));
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total = count($poList);
$poPage = array_slice($poList, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($total / $perPage));

$supplierLookup = [];
foreach ($suppliers as $s) {
    $supplierLookup[$s['id']] = $s['name'];
}

function format_money_po(int $c): string {
    return '$' . number_format($c / 100, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Orders</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Purchase Orders</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="suppliers.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Suppliers</a>
            <a href="purchase_orders.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Purchase Orders</a>
            <a href="expenses.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Expenses</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800"><?php echo $editPo ? 'Edit purchase order' : 'Create purchase order'; ?></h2>
        <form method="post" id="po_form" class="space-y-3">
            <input type="hidden" name="form_action" value="save_po">
            <?php if ($editPo): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPo['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">None</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $editPo && $editPo['supplier_id'] === $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full border rounded px-3 py-2 text-sm" value="<?php echo $editPo ? htmlspecialchars($editPo['notes'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-slate-800">Items</h3>
                    <button type="button" id="add_item" class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700">Add item</button>
                </div>
                <div id="items_container" class="space-y-2">
                    <?php
                    $rows = $editItems ?: [[]];
                    foreach ($rows as $row):
                    ?>
                        <div class="grid md:grid-cols-5 gap-2 item-row">
                            <select name="item_product_id[]" class="border rounded px-2 py-1 text-sm">
                                <option value="">(Custom)</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (!empty($row['product_id']) && $row['product_id'] === $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="item_description[]" placeholder="Description" class="border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="number" name="item_quantity[]" value="<?php echo isset($row['quantity']) ? (int)$row['quantity'] : 1; ?>" min="1" class="border rounded px-2 py-1 text-sm">
                            <input type="number" step="0.01" name="item_unit_cost[]" value="<?php echo isset($row['unit_cost_cents']) ? (int)$row['unit_cost_cents'] / 100 : 0; ?>" min="0" class="border rounded px-2 py-1 text-sm">
                            <button type="button" class="remove_item text-xs px-2 py-1 rounded bg-rose-100 text-rose-700">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm"><?php echo $editPo ? 'Update PO' : 'Save PO'; ?></button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Purchase orders</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search id/notes" class="border rounded px-3 py-2 text-sm">
                <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="purchase_orders.php" class="text-sm text-blue-600">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <?php if (!empty($poPage)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">PO ID</th>
                        <th class="py-2 pr-4">Supplier</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Ordered</th>
                        <th class="py-2">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($poPage as $po): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-mono text-xs"><?php echo htmlspecialchars($po['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?php echo !empty($po['supplier_id']) && isset($supplierLookup[$po['supplier_id']]) ? htmlspecialchars($supplierLookup[$po['supplier_id']], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td class="py-2 pr-4">
                                <span class="inline-flex px-2 py-1 rounded text-xs <?php echo $po['status'] === 'received' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                    <?php echo htmlspecialchars($po['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-slate-800"><?php echo htmlspecialchars(format_money_po((int)$po['total_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr((string)$po['ordered_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2">
                                <a href="purchase_orders.php?edit=<?php echo htmlspecialchars($po['id'], ENT_QUOTES, 'UTF-8'); ?>" class="text-xs text-blue-600 hover:underline mr-1">Edit</a>
                                <?php if ($po['status'] !== 'received'): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="form_action" value="receive_po">
                                        <input type="hidden" name="po_id" value="<?php echo htmlspecialchars($po['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800">Mark received</button>
                                    </form>
                                    <form method="post" class="inline" onsubmit="return confirm('Delete this PO?');">
                                        <input type="hidden" name="form_action" value="delete_po">
                                        <input type="hidden" name="po_id" value="<?php echo htmlspecialchars($po['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-700">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">Received</span>
                                <?php endif; ?>
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
                            <a href="purchase_orders.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="purchase_orders.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-600">No purchase orders yet.</p>
        <?php endif; ?>
    </section>
</main>
<script>
    (function(){
        var addBtn = document.getElementById('add_item');
        var container = document.getElementById('items_container');
        function bindRemove(btn){
            btn.addEventListener('click', function(){
                var row = btn.closest('.item-row');
                if (row && container.children.length > 1) {
                    row.remove();
                }
            });
        }
        Array.from(document.querySelectorAll('.remove_item')).forEach(bindRemove);
        addBtn.addEventListener('click', function(){
            var row = container.querySelector('.item-row');
            if (!row) return;
            var clone = row.cloneNode(true);
            Array.from(clone.querySelectorAll('input')).forEach(function(inp){ inp.value = inp.type === 'number' ? '1' : ''; });
            var sel = clone.querySelector('select');
            if (sel) sel.selectedIndex = 0;
            bindRemove(clone.querySelector('.remove_item'));
            container.appendChild(clone);
        });
    })();
</script>
</body>
</html>
