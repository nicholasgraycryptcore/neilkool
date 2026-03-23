<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin', 'editor']);
require __DIR__ . '/admin_b64_decode.php';

$snippets = load_snippets();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle create/update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    decode_b64_post();
    $name = trim($_POST['name'] ?? '');
    $language = trim($_POST['language'] ?? 'javascript');
    $code = $_POST['code'] ?? '';
    $auto_include = !empty($_POST['auto_include']);
    $snippet_id = $_POST['id'] ?? '';

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($snippet_id === '') {
            $snippet_id = generate_id();
        }
        if ($language === '') {
            $language = 'javascript';
        }
        save_snippet($snippet_id, $name, $language, $code, $auto_include);
        header('Location: snippets.php?success=1');
        exit;
    }

    $action = 'edit';
    $id = $snippet_id;
}

// Handle delete
if ($action === 'delete' && $id) {
    delete_snippet($id);
    header('Location: snippets.php?deleted=1');
    exit;
}

// Find snippet for editing
$current_snippet = null;
if ($action === 'edit' && $id) {
    $current_snippet = find_snippet_by_id($id);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Code Snippets - Website Builder Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="admin_b64.js"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Code Snippets</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Pages dashboard</a>
            <a href="parts.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Reusable parts</a>
            <a href="snippets.php?action=edit" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">New snippet</a>
            <a href="index.php" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">View site</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6">
    <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Snippet saved successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Snippet deleted.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Saved code snippets</h2>
            <a href="snippets.php?action=edit" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Create new snippet</a>
        </div>
        <?php if (empty($snippets)): ?>
            <p class="text-slate-600 bg-white border rounded px-4 py-3 text-sm">
                No code snippets yet. Create snippets for items like analytics tags, chat widgets, or other JavaScript/PHP code you want to reuse.
            </p>
        <?php else: ?>
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-50 text-left text-slate-700">
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Language</th>
                        <th class="px-3 py-2 font-medium">Auto-include</th>
                        <th class="px-3 py-2 font-medium">Last updated</th>
                        <th class="px-3 py-2 font-medium text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($snippets as $snippet): ?>
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium text-slate-900"><?php echo htmlspecialchars($snippet['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="mt-0.5 text-xs text-slate-500">ID: <code class="bg-slate-100 px-1 rounded"><?php echo htmlspecialchars($snippet['id'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-600">
                                <?php echo htmlspecialchars($snippet['language'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-600">
                                <?php if (!empty($snippet['auto_include'])): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Yes</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-500">
                                <?php echo htmlspecialchars($snippet['updated_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex justify-end gap-2 text-xs">
                                    <a class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800"
                                       href="snippets.php?action=edit&id=<?php echo urlencode($snippet['id']); ?>">Edit</a>
                                    <a class="inline-flex items-center px-2 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-800"
                                       href="snippets.php?action=delete&id=<?php echo urlencode($snippet['id']); ?>"
                                       onclick="return confirm('Delete this snippet? It will not remove code from existing pages where you already pasted it.');">
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
        $edit_id = $current_snippet['id'] ?? ($_POST['id'] ?? '');
        $edit_name = $current_snippet['name'] ?? ($_POST['name'] ?? '');
        $edit_language = $current_snippet['language'] ?? ($_POST['language'] ?? 'javascript');
        $edit_auto_include = isset($current_snippet['auto_include'])
            ? (bool)$current_snippet['auto_include']
            : (!empty($_POST['auto_include']));
        $default_code = "// Example: analytics script\n// Paste your JavaScript here.";
        $edit_code = $current_snippet['code'] ?? ($_POST['code'] ?? $default_code);
        ?>
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800"><?php echo $edit_id ? 'Edit snippet' : 'Create new snippet'; ?></h2>
            <p class="text-sm text-slate-500">
                Define reusable pieces of code such as analytics tags, chat widgets, or helper scripts. You can insert these into any page from the page editor.
            </p>
        </div>
        <form method="post" action="snippets.php" class="b64-form space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id, ENT_QUOTES, 'UTF-8'); ?>">

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Snippet name</label>
                <input type="text" id="name" name="name"
                       class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                       value="<?php echo htmlspecialchars($edit_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                <p class="mt-1 text-xs text-slate-500">Example: “Google Analytics”, “Live chat widget”, “Custom footer script”.</p>
            </div>

            <div>
                <label for="language" class="block text-sm font-medium text-slate-700">Language</label>
                <select id="language" name="language"
                        class="mt-1 block w-full rounded border-slate-300 shadow-sm bg-white focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <?php
                    $languages = ['javascript' => 'JavaScript', 'php' => 'PHP', 'html' => 'HTML', 'css' => 'CSS', 'other' => 'Other'];
                    foreach ($languages as $key => $label):
                        ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $edit_language === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">
                    This is just a label to help you keep snippets organized. The code is inserted exactly as you write it.
                </p>
            </div>

            <div class="flex items-start gap-2">
                <div class="pt-1">
                    <input id="auto_include" name="auto_include" type="checkbox"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        <?php echo $edit_auto_include ? 'checked' : ''; ?>>
                </div>
                <div>
                    <label for="auto_include" class="text-sm font-medium text-slate-700">Auto-include on all pages</label>
                    <p class="mt-0.5 text-xs text-slate-500">
                        When enabled, this snippet’s code is added to every page on your site. Ideal for analytics, tracking pixels, or global widgets.
                    </p>
                </div>
            </div>

            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
                <textarea id="code" name="code" rows="12"
                          class="mt-1 block w-full rounded border-slate-300 font-mono text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($edit_code, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    Paste your script or markup exactly as it should appear on the page. For JavaScript, include <code>&lt;script&gt;</code> tags if you want them executed.
                </p>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save snippet</button>
                <a href="snippets.php" class="inline-flex items-center px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
