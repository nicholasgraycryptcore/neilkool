<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$roles = [
    'admin' => 'Admin (full access)',
    'editor' => 'Editor (pages/content)',
    'cashier' => 'Cashier (POS)',
    'mid' => 'Mid (editor access)',
    'offlane' => 'Offlane (editor access)',
    'carry' => 'Carry (cashier access)',
    'support' => 'Support (admin access)',
    'soft support' => 'Soft support (editor access)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'admin';
        if ($username === '' || $password === '') {
            header('Location: users.php?err=' . rawurlencode('Username and password are required.'));
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: users.php?err=' . rawurlencode('Password must be at least 8 characters.'));
            exit;
        }
        if (!isset($roles[$role])) {
            $role = 'admin';
        }
        try {
            create_user($username, $password, $role);
            log_action('create_user', current_user()['username'] ?? 'admin', 'user', null, ['username' => $username, 'role' => $role]);
            header('Location: users.php?msg=' . rawurlencode('User created.'));
            exit;
        } catch (Throwable $e) {
            header('Location: users.php?err=' . rawurlencode('Could not create user: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'update_role') {
        $id = $_POST['user_id'] ?? '';
        $role = $_POST['role'] ?? 'admin';
        if ($id === '' || !isset($roles[$role])) {
            header('Location: users.php?err=' . rawurlencode('Invalid user or role.'));
            exit;
        }
        update_user_role($id, $role);
        log_action('update_user_role', current_user()['username'] ?? 'admin', 'user', $id, ['role' => $role]);
        header('Location: users.php?msg=' . rawurlencode('Role updated.'));
        exit;
    } elseif ($action === 'reset_password') {
        $id = $_POST['user_id'] ?? '';
        $password = trim($_POST['password'] ?? '');
        if ($id === '' || $password === '') {
            header('Location: users.php?err=' . rawurlencode('User and password required.'));
            exit;
        }
        if (strlen($password) < 8) {
            header('Location: users.php?err=' . rawurlencode('Password must be at least 8 characters.'));
            exit;
        }
        update_user_password($id, $password);
        log_action('reset_password', current_user()['username'] ?? 'admin', 'user', $id, []);
        header('Location: users.php?msg=' . rawurlencode('Password updated.'));
        exit;
    }
}

$users_raw = load_users();
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $users_raw = array_values(array_filter($users_raw, function ($u) use ($search) {
        return stripos($u['username'], $search) !== false;
    }));
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$totalUsers = count($users_raw);
$users = array_slice($users_raw, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">User Management</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="users.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Users</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Add user</h2>
        <form method="post" class="grid md:grid-cols-3 gap-3">
            <input type="hidden" name="form_action" value="create_user">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" name="username" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select name="role" class="w-full border rounded px-3 py-2 text-sm">
                    <?php foreach ($roles as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Create</button>
            </div>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Users</h2>
        <form method="get" class="flex items-center gap-2">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search username" class="border rounded px-3 py-2 text-sm w-64">
            <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="users.php" class="text-sm text-blue-600">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (!empty($users)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Username</th>
                        <th class="py-2 pr-4">Role</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4">
                                <form method="post" class="flex items-center gap-2">
                                    <input type="hidden" name="form_action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <select name="role" class="border rounded px-2 py-1 text-sm">
                                        <?php foreach ($roles as $key => $label): ?>
                                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $user['role'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700">Update</button>
                                </form>
                            </td>
                            <td class="py-2 pr-4">
                                <form method="post" class="flex items-center gap-2">
                                    <input type="hidden" name="form_action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="password" name="password" placeholder="New password" class="border rounded px-2 py-1 text-sm">
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-emerald-200 text-emerald-800">Reset</button>
                                </form>
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
                            <a href="users.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="users.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-600">No users yet.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
