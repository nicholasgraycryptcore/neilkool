<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);
require __DIR__ . '/admin_nav.php';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    if (!empty($_FILES['media_file']['tmp_name']) && is_uploaded_file($_FILES['media_file']['tmp_name'])) {
        $originalName = $_FILES['media_file']['name'] ?? 'file';
        $tmpPath = $_FILES['media_file']['tmp_name'];
        $size = (int)($_FILES['media_file']['size'] ?? 0);
        $mimeType = $_FILES['media_file']['type'] ?? null;

        $ext = '';
        if (strpos($originalName, '.') !== false) {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }

        $id = generate_id();
        $safeExt = $ext !== '' ? preg_replace('/[^a-z0-9]+/i', '', $ext) : 'bin';
        $filename = $id . '.' . $safeExt;
        $targetPath = MEDIA_DIR . '/' . $filename;

        if (move_uploaded_file($tmpPath, $targetPath)) {
            save_media($id, $filename, $originalName, $mimeType, $size);
            header('Location: media.php?uploaded=1');
            exit;
        } else {
            $error = 'Failed to save uploaded file.';
        }
    } else {
        $error = 'No file selected or upload failed.';
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    delete_media($_GET['id']);
    header('Location: media.php?deleted=1');
    exit;
}

$media_all = load_media();
$media_search = trim($_GET['q'] ?? '');
if ($media_search !== '') {
    $media_all = array_values(array_filter($media_all, function ($m) use ($media_search) {
        return stripos($m['original_name'], $media_search) !== false || stripos($m['filename'], $media_search) !== false;
    }));
}
$media_page = max(1, (int)($_GET['page'] ?? 1));
$media_per_page = 12;
$media_total = count($media_all);
$media_items = array_slice($media_all, ($media_page - 1) * $media_per_page, $media_per_page);
$media_total_pages = max(1, (int)ceil($media_total / $media_per_page));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Media library - Website Builder Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<?php render_admin_sidebar('media'); ?>
<main class="max-w-5xl mx-auto px-4 py-6">
    <?php if (isset($_GET['uploaded'])): ?>
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Media uploaded successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Media item deleted.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="get" class="mb-4 flex items-center gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($media_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search media" class="border rounded px-3 py-2 text-sm w-64">
        <button type="submit" class="px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
        <?php if ($media_search !== ''): ?>
            <a href="media.php" class="text-sm text-blue-600">Clear</a>
        <?php endif; ?>
    </form>

    <section class="mb-6 rounded border border-slate-200 bg-white p-4">
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Upload new media</h2>
        <p class="mb-3 text-xs text-slate-500">Upload images, videos, audio, or other files. You can quickly insert them into page content from the page editor.</p>
        <form method="post" action="media.php" enctype="multipart/form-data" class="space-y-3">
            <div>
                <label for="media_file" class="block text-sm font-medium text-slate-700">Choose file</label>
                <input type="file" id="media_file" name="media_file"
                       class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-slate-200">
                <p class="mt-1 text-xs text-slate-500">Most common formats are supported (JPG, PNG, GIF, MP4, MP3, etc.).</p>
            </div>
            <div class="pt-1 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Upload</button>
            </div>
        </form>
    </section>

    <section>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-semibold text-slate-800">Existing media</h2>
            <p class="text-xs text-slate-500">Use the URLs or the insert buttons in the page editor to attach media.</p>
        </div>
        <?php if (empty($media_items)): ?>
            <p class="text-slate-600 bg-white border rounded px-4 py-3 text-sm">No media uploaded yet. Use the form above to add your first image or file.</p>
        <?php else: ?>
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="min-w-full text-xs">
                    <thead>
                    <tr class="bg-slate-50 text-left text-slate-700">
                        <th class="px-3 py-2 font-medium">Preview / name</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">URL</th>
                        <th class="px-3 py-2 font-medium">Size</th>
                        <th class="px-3 py-2 font-medium">Uploaded</th>
                        <th class="px-3 py-2 font-medium text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($media_items as $item): ?>
                        <?php
                        $url = 'data/media/' . rawurlencode($item['filename']);
                        $mime = strtolower((string)$item['mime_type']);
                        $isImage = strpos($mime, 'image/') === 0;
                        $isVideo = strpos($mime, 'video/') === 0;
                        $isAudio = strpos($mime, 'audio/') === 0;
                        ?>
                        <tr class="border-t border-slate-100 align-top">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <?php if ($isImage): ?>
                                        <img src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="h-10 w-10 object-cover rounded border border-slate-200">
                                    <?php else: ?>
                                        <div class="h-10 w-10 flex items-center justify-center rounded border border-slate-200 bg-slate-50 text-[10px] uppercase text-slate-500">
                                            <?php echo $isVideo ? 'Video' : ($isAudio ? 'Audio' : 'File'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-medium text-slate-900 truncate max-w-[180px]" title="<?php echo htmlspecialchars($item['original_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($item['original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="text-[11px] text-slate-400">ID: <code class="bg-slate-100 px-1 rounded"><?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-[11px] text-slate-600">
                                <?php echo htmlspecialchars($item['mime_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-2 text-[11px] text-slate-600">
                                <input type="text" readonly value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="w-full rounded border-slate-200 bg-slate-50 px-1 py-0.5 text-[11px]">
                            </td>
                            <td class="px-3 py-2 text-[11px] text-slate-600">
                                <?php
                                $size = (int)($item['size'] ?? 0);
                                if ($size > 0) {
                                    if ($size >= 1048576) {
                                        echo round($size / 1048576, 1) . ' MB';
                                    } elseif ($size >= 1024) {
                                        echo round($size / 1024, 1) . ' KB';
                                    } else {
                                        echo $size . ' B';
                                    }
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 text-[11px] text-slate-500">
                                <?php echo htmlspecialchars($item['uploaded_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-3 py-2 text-right text-[11px]">
                                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"
                                   class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 mr-1">Open</a>
                                <a href="media.php?action=delete&id=<?php echo urlencode($item['id']); ?>"
                                   class="inline-flex items-center px-2 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-800"
                                   onclick="return confirm('Delete this media file? This will not automatically remove links inside your pages.');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($media_total_pages > 1): ?>
                <div class="flex items-center justify-between mt-3 text-sm text-slate-700">
                    <div>Page <?php echo $media_page; ?> of <?php echo $media_total_pages; ?></div>
                    <div class="space-x-2">
                        <?php if ($media_page > 1): ?>
                            <a href="media.php?page=<?php echo $media_page - 1; ?>&q=<?php echo urlencode($media_search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($media_page < $media_total_pages): ?>
                            <a href="media.php?page=<?php echo $media_page + 1; ?>&q=<?php echo urlencode($media_search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?php render_admin_sidebar_close(); ?>
</body>
</html>
