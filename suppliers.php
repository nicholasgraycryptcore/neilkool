<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        header('Location: suppliers.php?err=' . rawurlencode('Name is required.'));
        exit;
    }
    try {
        $id = save_supplier([
            'id' => $_POST['id'] ?? null,
            'name' => $name,
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        log_action('save_supplier', current_user()['username'] ?? 'admin', 'supplier', $id, ['name' => $name]);
        header('Location: suppliers.php?msg=' . rawurlencode('Supplier saved.'));
        exit;
    } catch (Throwable $e) {
        header('Location: suppliers.php?err=' . rawurlencode('Could not save supplier.'));
        exit;
    }
}

$suppliers = load_suppliers();
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $suppliers = array_values(array_filter($suppliers, function ($s) use ($search) {
        return stripos($s['name'], $search) !== false;
    }));
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$total = count($suppliers);
$suppliersPage = array_slice($suppliers, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($total / $perPage));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Suppliers</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Suppliers</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="suppliers.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Suppliers</a>
            <a href="purchase_orders.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Purchase Orders</a>
            <a href="expenses.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Expenses</a>
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
        <h2 class="text-lg font-semibold text-slate-800">Add supplier</h2>
        <form method="post" class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contact name</label>
                <input type="text" name="contact_name" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="contact_email" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                <input type="text" name="contact_phone" class="w-full border rounded px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Save</button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Suppliers</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search name" class="border rounded px-3 py-2 text-sm">
                <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="suppliers.php" class="text-sm text-blue-600">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <?php if (!empty($suppliersPage)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Contact</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Phone</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($suppliersPage as $s): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars($s['contact_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars($s['contact_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars($s['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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
                            <a href="suppliers.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="suppliers.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-600">No suppliers yet.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
