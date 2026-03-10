<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$backupDir = DATA_DIR . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

function create_db_backup(string $backupDir): string
{
    $ts = date('Ymd_His');
    $target = $backupDir . "/site_{$ts}.sqlite";
    if (!copy(DB_FILE, $target)) {
        throw new RuntimeException('Failed to copy database.');
    }
    return $target;
}

function create_media_zip(string $backupDir): string
{
    $ts = date('Ymd_His');
    $zipPath = $backupDir . "/media_{$ts}.zip";
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Could not create zip archive.');
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(MEDIA_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen(MEDIA_DIR) + 1);
        $zip->addFile($filePath, $relativePath);
    }
    $zip->close();
    return $zipPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'backup_db') {
            $path = create_db_backup($backupDir);
            header('Location: backup.php?msg=' . rawurlencode('Database backup created: ' . basename($path)));
            exit;
        } elseif ($action === 'backup_media') {
            $path = create_media_zip($backupDir);
            header('Location: backup.php?msg=' . rawurlencode('Media backup created: ' . basename($path)));
            exit;
        } elseif ($action === 'restore_db') {
            $file = $_POST['backup_file'] ?? '';
            $full = realpath($backupDir . '/' . $file);
            if (!$full || strpos($full, realpath($backupDir)) !== 0 || !is_file($full)) {
                throw new RuntimeException('Invalid backup file.');
            }
            if (!copy($full, DB_FILE)) {
                throw new RuntimeException('Restore failed.');
            }
            header('Location: backup.php?msg=' . rawurlencode('Database restored. Consider restarting the app.'));
            exit;
        }
    } catch (Throwable $e) {
        header('Location: backup.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$backups = glob($backupDir . '/*');
usort($backups, function($a, $b){ return filemtime($b) <=> filemtime($a); });

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Backups</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Backups</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Dashboard</a>
            <a href="backup.php" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700">Backups</a>
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
        <h2 class="text-lg font-semibold text-slate-800">Create backup</h2>
        <form method="post" class="flex items-center gap-2">
            <input type="hidden" name="action" value="backup_db">
            <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Backup database</button>
        </form>
        <form method="post" class="flex items-center gap-2">
            <input type="hidden" name="action" value="backup_media">
            <button type="submit" class="px-4 py-2 rounded bg-slate-700 hover:bg-slate-800 text-white text-sm">Backup media (zip)</button>
        </form>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-3">
        <h2 class="text-lg font-semibold text-slate-800">Restore database</h2>
        <?php if (!empty($backups)): ?>
            <form method="post" class="flex items-center gap-2">
                <input type="hidden" name="action" value="restore_db">
                <select name="backup_file" class="border rounded px-3 py-2 text-sm">
                    <?php foreach ($backups as $b): ?>
                        <option value="<?php echo htmlspecialchars(basename($b), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(basename($b), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 rounded bg-rose-600 hover:bg-rose-700 text-white text-sm" onclick="return confirm('Restore this backup? This will overwrite the current database.');">Restore</button>
            </form>
            <p class="text-xs text-slate-500">Restore replaces `data/site.sqlite`. Media restore is not automatic; use the media backup zip manually if needed.</p>
        <?php else: ?>
            <p class="text-sm text-slate-600">No backups yet.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-2">
        <h2 class="text-lg font-semibold text-slate-800">Existing backups</h2>
        <?php if (!empty($backups)): ?>
            <ul class="text-sm text-slate-700 space-y-1">
                <?php foreach ($backups as $b): ?>
                    <li>
                        <a class="text-blue-600 hover:underline" href="<?php echo htmlspecialchars(str_replace(DATA_DIR, 'data', $b), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(basename($b), ENT_QUOTES, 'UTF-8'); ?></a>
                        <span class="text-xs text-slate-500 ml-2"><?php echo date('Y-m-d H:i', filemtime($b)); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-600">No backup files found.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
