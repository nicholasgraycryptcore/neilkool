<?php
require __DIR__ . '/auth.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">
<div class="w-full max-w-md bg-white shadow rounded-lg px-6 py-6">
    <h1 class="text-lg font-semibold text-slate-800 mb-1">Admin login</h1>
    <p class="text-xs text-slate-500 mb-4">Sign in to manage your website pages.</p>
    <?php if ($error): ?>
        <div class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" class="space-y-3">
        <div>
            <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
            <input type="text" id="username" name="username" required
                   class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
        </div>

        <button type="submit"
                class="mt-2 w-full inline-flex justify-center items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
            Log in
        </button>
    </form>
</div>
</body>
</html>
