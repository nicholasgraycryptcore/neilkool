<?php
require __DIR__ . '/auth.php';
require_login();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = trim($_POST['password'] ?? '');
    if ($new === '') {
        header('Location: change_password.php?err=' . rawurlencode('Password is required.'));
        exit;
    }
    if (strlen($new) < 8) {
        header('Location: change_password.php?err=' . rawurlencode('Password must be at least 8 characters.'));
        exit;
    }
    try {
        update_user_password($user['id'], $new);
        unset($_SESSION['force_password_change']);
        log_action('change_password', $user['username'], 'user', $user['id'], []);
        header('Location: admin.php?msg=' . rawurlencode('Password updated.'));
        exit;
    } catch (Throwable $e) {
        header('Location: change_password.php?err=' . rawurlencode('Failed to update password.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="bg-white border border-slate-200 rounded shadow-sm p-6 w-full max-w-md">
        <h1 class="text-xl font-semibold text-slate-900 mb-3">Change password</h1>
        <?php if ($msg !== ''): ?>
            <div class="mb-3 rounded bg-emerald-100 text-emerald-800 px-3 py-2 text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
            <div class="mb-3 rounded bg-rose-100 text-rose-800 px-3 py-2 text-sm"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">New password</label>
                <input type="password" name="password" class="w-full border rounded px-3 py-2 text-sm" required>
            </div>
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Update</button>
        </form>
    </div>
</body>
</html>
