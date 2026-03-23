<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin', 'editor']);
require __DIR__ . '/admin_b64_decode.php';

$parts = load_parts();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle create/update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    decode_b64_post();
    $name = trim($_POST['name'] ?? '');
    $content = $_POST['content'] ?? '';
    $part_id = $_POST['id'] ?? '';

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($part_id === '') {
            $part_id = generate_id();
        }
        save_part($part_id, $name, $content);
        header('Location: parts.php?success=1');
        exit;
    }

    $action = 'edit';
    $id = $part_id;
}

// Handle delete
if ($action === 'delete' && $id) {
    delete_part($id);
    header('Location: parts.php?deleted=1');
    exit;
}

// Find part for editing
$current_part = null;
if ($action === 'edit' && $id) {
    $current_part = find_part_by_id($id);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reusable Parts - Website Builder Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="admin_b64.js"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Reusable Parts</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Pages dashboard</a>
            <a href="parts.php?action=edit" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">New part</a>
            <a href="index.php" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">View site</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6">
    <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Part saved successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Part deleted.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Saved parts</h2>
            <a href="parts.php?action=edit" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Create new part</a>
        </div>
        <?php if (empty($parts)): ?>
            <p class="text-slate-600 bg-white border rounded px-4 py-3 text-sm">
                No reusable parts yet. Create a part for items like headers, footers, announcement bars, or call-to-action sections that you want to reuse on multiple pages.
            </p>
        <?php else: ?>
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-50 text-left text-slate-700">
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Last updated</th>
                        <th class="px-3 py-2 font-medium text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($parts as $part): ?>
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium text-slate-900"><?php echo htmlspecialchars($part['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="mt-0.5 text-xs text-slate-500">ID: <code class="bg-slate-100 px-1 rounded"><?php echo htmlspecialchars($part['id'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-500">
                                <?php echo htmlspecialchars($part['updated_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex justify-end gap-2 text-xs">
                                    <a class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800"
                                       href="parts.php?action=edit&id=<?php echo urlencode($part['id']); ?>">Edit</a>
                                    <a class="inline-flex items-center px-2 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-800"
                                       href="parts.php?action=delete&id=<?php echo urlencode($part['id']); ?>"
                                       onclick="return confirm('Delete this part? It will not remove content from existing pages where you already pasted it.');">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php elseif ($action === 'edit'): ?>
        <?php
        $edit_id = $current_part['id'] ?? ($_POST['id'] ?? '');
        $edit_name = $current_part['name'] ?? ($_POST['name'] ?? '');
        $edit_content = $current_part['content'] ?? ($_POST['content'] ?? "<footer style=\"padding:20px;text-align:center;background:#111827;color:#e5e7eb;\">\n  <p>&copy; " . date('Y') . " Your Company. All rights reserved.</p>\n</footer>");
        ?>
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800"><?php echo $edit_id ? 'Edit part' : 'Create new part'; ?></h2>
            <p class="text-sm text-slate-500">
                Define reusable sections like headers, footers, or call-to-action blocks. You can insert these into any page from the page editor.
            </p>
        </div>
        <form method="post" action="parts.php" class="b64-form space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id, ENT_QUOTES, 'UTF-8'); ?>">

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Part name</label>
                <input type="text" id="name" name="name"
                       class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                       value="<?php echo htmlspecialchars($edit_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                <p class="mt-1 text-xs text-slate-500">Example: “Site footer”, “Pricing hero”, “Announcement bar”.</p>
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-slate-700">HTML content</label>
                <textarea id="content" name="content" rows="10"
                          class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"><?php echo htmlspecialchars($edit_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    Paste or write HTML here. This content will be inserted directly into pages where you use this part.
                </p>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save part</button>
                <a href="parts.php" class="inline-flex items-center px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
