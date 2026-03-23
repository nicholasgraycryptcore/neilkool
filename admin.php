<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin', 'editor']);
require __DIR__ . '/admin_b64_decode.php';
require __DIR__ . '/admin_nav.php';

$pages = load_pages();
$home_page_id = get_setting('home_page_id');
$parts = load_parts();
$snippets = load_snippets();
$media_items = load_media();

// Site-wide appearance and navigation settings
$site_title = get_setting('site_title', 'Your Site');
$site_logo_url = get_setting('site_logo_url', '');

// Global default menu (fallback)
$raw_menu_items = get_setting('site_menu_items', '[]');
$site_menu_items = [];
if ($raw_menu_items !== '') {
    $decoded = json_decode($raw_menu_items, true);
    if (is_array($decoded)) {
        $site_menu_items = $decoded;
    }
}

// Home page-specific menu (overrides global on home pages)
$raw_menu_home = get_setting('site_menu_home_items', '');
$site_menu_home_items = [];
if ($raw_menu_home !== '') {
    $decoded_home = json_decode($raw_menu_home, true);
    if (is_array($decoded_home)) {
        $site_menu_home_items = $decoded_home;
    }
}

// Per-template menus for inner pages (override global when set)
$raw_menu_default = get_setting('site_menu_page_default_items', '');
$site_menu_page_default_items = [];
if ($raw_menu_default !== '') {
    $decoded_default = json_decode($raw_menu_default, true);
    if (is_array($decoded_default)) {
        $site_menu_page_default_items = $decoded_default;
    }
}

$raw_menu_dark = get_setting('site_menu_page_dark_items', '');
$site_menu_page_dark_items = [];
if ($raw_menu_dark !== '') {
    $decoded_dark = json_decode($raw_menu_dark, true);
    if (is_array($decoded_dark)) {
        $site_menu_page_dark_items = $decoded_dark;
    }
}

$raw_menu_minimal = get_setting('site_menu_page_minimal_items', '');
$site_menu_page_minimal_items = [];
if ($raw_menu_minimal !== '') {
    $decoded_minimal = json_decode($raw_menu_minimal, true);
    if (is_array($decoded_minimal)) {
        $site_menu_page_minimal_items = $decoded_minimal;
    }
}

$show_admin_link = get_setting('show_admin_link', '1') !== '0';

$templates = [
    'default' => 'Default',
    'dark' => 'Dark',
    'minimal' => 'Minimal',
];

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle create/update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    decode_b64_post();
    // Handle settings updates (like landing page, site appearance) separately
    if (isset($_POST['settings_action'])) {
        if ($_POST['settings_action'] === 'set_home_page') {
            $selected_id = trim($_POST['home_page_id'] ?? '');

            if ($selected_id === '') {
                // Clear landing page
                set_setting('home_page_id', null);
            } else {
                // Only save if the page exists
                $exists = false;
                foreach ($pages as $page) {
                    if ($page['id'] === $selected_id) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    set_setting('home_page_id', $selected_id);
                }
            }

            header('Location: admin.php?home_saved=1');
            exit;
        } elseif ($_POST['settings_action'] === 'save_site_settings') {
            $new_title = trim($_POST['site_title'] ?? '');
            $new_logo_url = trim($_POST['site_logo_url'] ?? '');
            $new_show_admin_link = !empty($_POST['show_admin_link']) ? '1' : '0';

            if ($new_title === '') {
                $new_title = 'Your Site';
            }

            set_setting('site_title', $new_title);
            set_setting('site_logo_url', $new_logo_url !== '' ? $new_logo_url : null);
            set_setting('show_admin_link', $new_show_admin_link);

            // Helper to build menu arrays from POST label/url arrays
            $buildMenu = function (array $labels, array $urls): array {
                $items = [];
                foreach ($labels as $index => $label) {
                    $label = trim((string)$label);
                    $url = trim((string)($urls[$index] ?? ''));
                    if ($label === '' && $url === '') {
                        continue;
                    }
                    $items[] = [
                        'label' => $label !== '' ? $label : $url,
                        'url' => $url !== '' ? $url : '#',
                    ];
                }
                return $items;
            };

            // Global fallback menu
            $global_labels = $_POST['menu_label'] ?? [];
            $global_urls = $_POST['menu_url'] ?? [];
            $global_menu_items = $buildMenu($global_labels, $global_urls);
            set_setting('site_menu_items', !empty($global_menu_items) ? json_encode($global_menu_items) : null);

            // Home page-specific menu
            $home_labels = $_POST['menu_home_label'] ?? [];
            $home_urls = $_POST['menu_home_url'] ?? [];
            $home_menu_items = $buildMenu($home_labels, $home_urls);
            set_setting('site_menu_home_items', !empty($home_menu_items) ? json_encode($home_menu_items) : null);

            // Per-template menus
            $default_labels = $_POST['menu_default_label'] ?? [];
            $default_urls = $_POST['menu_default_url'] ?? [];
            $default_menu_items = $buildMenu($default_labels, $default_urls);
            set_setting('site_menu_page_default_items', !empty($default_menu_items) ? json_encode($default_menu_items) : null);

            $dark_labels = $_POST['menu_dark_label'] ?? [];
            $dark_urls = $_POST['menu_dark_url'] ?? [];
            $dark_menu_items = $buildMenu($dark_labels, $dark_urls);
            set_setting('site_menu_page_dark_items', !empty($dark_menu_items) ? json_encode($dark_menu_items) : null);

            $minimal_labels = $_POST['menu_minimal_label'] ?? [];
            $minimal_urls = $_POST['menu_minimal_url'] ?? [];
            $minimal_menu_items = $buildMenu($minimal_labels, $minimal_urls);
            set_setting('site_menu_page_minimal_items', !empty($minimal_menu_items) ? json_encode($minimal_menu_items) : null);

            header('Location: admin.php?site_saved=1');
            exit;
        }
    }

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $page_css = '';
    $page_js = '';
    $full_width = !empty($_POST['full_width']) ? 1 : 0;

    // CSS and JS come as file uploads to bypass Cloudflare WAF
    if (!empty($_FILES['page_css_file']['tmp_name']) && is_uploaded_file($_FILES['page_css_file']['tmp_name'])) {
        $page_css = file_get_contents($_FILES['page_css_file']['tmp_name']);
    }
    if (!empty($_FILES['page_js_file']['tmp_name']) && is_uploaded_file($_FILES['page_js_file']['tmp_name'])) {
        $page_js = file_get_contents($_FILES['page_js_file']['tmp_name']);
    }
    $show_title_flag = !empty($_POST['show_title']) ? 1 : 0;
    $show_meta_flag = !empty($_POST['show_meta']) ? 1 : 0;
    $template = $_POST['template'] ?? 'default';

    if (!array_key_exists($template, $templates)) {
        $template = 'default';
    }

    // If an HTML file is uploaded, use its contents as the page content
    if (!empty($_FILES['html_file']['tmp_name']) && is_uploaded_file($_FILES['html_file']['tmp_name'])) {
        $uploadedContent = file_get_contents($_FILES['html_file']['tmp_name']);
        if ($uploadedContent !== false && trim($uploadedContent) !== '') {
            $content = $uploadedContent;
        }
    }

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        if ($slug === '') {
            $slug = slugify($title);
        } else {
            $slug = slugify($slug);
        }

        $is_new = empty($_POST['id']);
        $page_id = $is_new ? generate_id() : $_POST['id'];

        // Ensure slug is unique
        foreach ($pages as $existing) {
            if ($existing['slug'] === $slug && $existing['id'] !== $page_id) {
                $error = 'Slug must be unique.';
                break;
            }
        }
    }

    if (!isset($error)) {
        $new_page = [
            'id' => $page_id,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'template' => $template,
            'page_css' => $page_css,
            'full_width' => $full_width,
            'show_title' => $show_title_flag,
            'show_meta' => $show_meta_flag,
            'updated_at' => date('c'),
        ];

        $found = false;
        foreach ($pages as $index => $page) {
            if ($page['id'] === $page_id) {
                $pages[$index] = $new_page;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $pages[] = $new_page;
        }

        save_pages($pages);

        // Write CSS/JS to static asset files (served directly, never POSTed again)
        write_page_asset($page_id, 'css', $page_css);
        write_page_asset($page_id, 'js', $page_js);

        header('Location: admin.php?success=1');
        exit;
    } else {
        $action = 'edit';
        $id = $_POST['id'] ?? null;
    }
}

// Handle delete
if ($action === 'delete' && $id) {
    $pages = array_values(array_filter($pages, function ($page) use ($id) {
        return $page['id'] !== $id;
    }));

    // If the deleted page was the landing page, clear the setting
    if ($home_page_id === $id) {
        set_setting('home_page_id', null);
    }

    save_pages($pages);
    header('Location: admin.php?deleted=1');
    exit;
}

// Find page for editing
$current_page = null;
if ($action === 'edit' && $id) {
    $current_page = find_page_by_id($pages, $id);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Website Builder Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tiny.cloud/1/<?php echo rawurlencode(TINYMCE_API_KEY); ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body .tox {
           height:500px !important;
        }
    </style>
    <script src="admin_b64.js"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<?php render_admin_sidebar('dashboard'); ?>
<main class="w-full mx-auto px-4 py-6 max-w-none">
    <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Page saved successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">Page deleted.</div>
    <?php endif; ?>

    <?php if (isset($_GET['home_saved'])): ?>
        <div class="mb-4 rounded border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-800">Landing page updated.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Pages</h2>
                <?php if (!empty($pages)): ?>
                    <form method="post" action="admin.php" class="b64-form mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                        <input type="hidden" name="settings_action" value="set_home_page">
                        <label for="home_page_id" class="mr-1">Landing page:</label>
                        <select id="home_page_id" name="home_page_id" class="rounded border-slate-300 bg-white px-2 py-1 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Show page list as home</option>
                            <?php foreach ($pages as $page_option): ?>
                                <option value="<?php echo htmlspecialchars($page_option['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php if ($home_page_id === $page_option['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($page_option['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="inline-flex items-center rounded bg-slate-800 px-2.5 py-1 text-xs font-medium text-white hover:bg-slate-700">Save</button>
                    </form>
                <?php endif; ?>
            </div>
            <a href="admin.php?action=edit" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Create new page</a>
        </div>
        <?php if (empty($pages)): ?>
            <p class="text-slate-600 bg-white border rounded px-4 py-3">No pages yet. <a href="admin.php?action=edit" class="text-blue-600 underline">Create your first page</a>.</p>
        <?php else: ?>
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="bg-slate-50 text-left text-slate-700">
                    <th class="px-3 py-2 font-medium">Title</th>
                    <th class="px-3 py-2 font-medium">Slug / URL</th>
                    <th class="px-3 py-2 font-medium">Last updated</th>
                    <th class="px-3 py-2 font-medium text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 align-top">
                            <div class="font-medium text-slate-900"><?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                Template: <?php echo htmlspecialchars($page['template'] ?? 'default', ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($home_page_id === $page['id']): ?>
                                    <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Landing page</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top text-xs text-slate-700">
                            <div><code class="bg-slate-100 px-1 rounded"><?php echo htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8'); ?></code></div>
                            <a class="text-blue-600 hover:underline" href="<?php echo htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Open page</a>
                        </td>
                        <td class="px-3 py-2 align-top text-xs text-slate-500"><?php echo htmlspecialchars($page['updated_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="px-3 py-2 align-top">
                            <div class="flex justify-end gap-2 text-xs">
                                <a class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800" href="admin.php?action=edit&id=<?php echo urlencode($page['id']); ?>">Edit</a>
                                <a class="inline-flex items-center px-2 py-1 rounded bg-emerald-100 hover:bg-emerald-200 text-emerald-800" href="element_editor.php?id=<?php echo urlencode($page['id']); ?>">Quick text edit</a>
                                <a class="inline-flex items-center px-2 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-800" href="admin.php?action=delete&id=<?php echo urlencode($page['id']); ?>"
                                   onclick="return confirm('Delete this page?');">Delete</a>
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
        $edit_id = $current_page['id'] ?? ($_POST['id'] ?? '');
        $edit_title = $current_page['title'] ?? ($_POST['title'] ?? '');
        $edit_slug = $current_page['slug'] ?? ($_POST['slug'] ?? '');
        $edit_content = $current_page['content'] ?? ($_POST['content'] ?? "<h1>Your page title</h1>\n<p>Start writing here...</p>");
        // Load CSS/JS from static asset files (or fall back to DB for legacy pages)
        $edit_css = '';
        $edit_js = '';
        if ($edit_id !== '') {
            $edit_css = read_page_asset($edit_id, 'css');
            $edit_js = read_page_asset($edit_id, 'js');
            // Fall back to DB field for legacy pages not yet migrated
            if ($edit_css === '' && !empty($current_page['page_css'])) {
                $edit_css = $current_page['page_css'];
            }
        }
        $edit_full_width = isset($current_page['full_width']) ? (bool)$current_page['full_width'] : !empty($_POST['full_width']);
        $edit_show_title = isset($current_page['show_title']) ? (bool)$current_page['show_title'] : (!isset($current_page['show_title']) && !isset($_POST['show_title']) ? true : !empty($_POST['show_title']));
        $edit_show_meta = isset($current_page['show_meta']) ? (bool)$current_page['show_meta'] : (!isset($current_page['show_meta']) && !isset($_POST['show_meta']) ? true : !empty($_POST['show_meta']));
        $edit_template = $current_page['template'] ?? ($_POST['template'] ?? 'default');
        ?>
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800"><?php echo $edit_id ? 'Edit page' : 'Create new page'; ?></h2>
            <p class="text-sm text-slate-500">Fill in the basic details, then use the visual editor to design the page content.</p>
        </div>
        <form method="post" action="admin.php" enctype="multipart/form-data" class="b64-form space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id, ENT_QUOTES, 'UTF-8'); ?>">

            <div>
                <label for="title" class="block text-sm font-medium text-slate-700">Page title</label>
                <input type="text" id="title" name="title"
                       class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                       value="<?php echo htmlspecialchars($edit_title, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-slate-700">Web address (slug)</label>
                <input type="text" id="slug" name="slug"
                       placeholder="leave blank to auto-generate"
                       class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                       value="<?php echo htmlspecialchars($edit_slug, ENT_QUOTES, 'UTF-8'); ?>">
                <p class="mt-1 text-xs text-slate-500">This appears in the URL, for example: <code>/index.php?page=about-us</code>.</p>
            </div>

            <div>
                <label for="template" class="block text-sm font-medium text-slate-700">Look &amp; feel (template)</label>
                <select id="template" name="template"
                        class="mt-1 block w-full rounded border-slate-300 shadow-sm bg-white focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <?php foreach ($templates as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $edit_template === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-start gap-2">
                <div class="pt-1">
                    <input id="full_width" name="full_width" type="checkbox"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        <?php echo $edit_full_width ? 'checked' : ''; ?>>
                </div>
                <div>
                    <label for="full_width" class="text-sm font-medium text-slate-700">Use full-width content layout</label>
                    <p class="mt-0.5 text-xs text-slate-500">When checked, the page content area stretches edge-to-edge instead of being centered in a narrower column.</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="flex items-start gap-2">
                    <div class="pt-1">
                        <input id="show_title" name="show_title" type="checkbox"
                               class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            <?php echo $edit_show_title ? 'checked' : ''; ?>>
                    </div>
                    <div>
                        <label for="show_title" class="text-sm font-medium text-slate-700">Show page title on page</label>
                        <p class="mt-0.5 text-xs text-slate-500">Uncheck to hide the large page title heading above the content.</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="pt-1">
                        <input id="show_meta" name="show_meta" type="checkbox"
                               class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            <?php echo $edit_show_meta ? 'checked' : ''; ?>>
                    </div>
                    <div>
                        <label for="show_meta" class="text-sm font-medium text-slate-700">Show page meta (slug / last updated)</label>
                        <p class="mt-0.5 text-xs text-slate-500">Uncheck to hide the small “URL slug / Last updated” line.</p>
                    </div>
                </div>
            </div>

            <div>
                <label for="html_file" class="block text-sm font-medium text-slate-700">Upload a full page (optional)</label>
                <input type="file" id="html_file" name="html_file" accept=".html,.htm,text/html"
                       class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-slate-200">
                <p class="mt-1 text-xs text-slate-500">If you have a ready-made HTML page from a designer, upload it here and then tweak text using the visual editor or quick text editor.</p>
            </div>

            <div class="space-y-4">
                <div class="space-y-1">
                    <label for="content" class="block text-sm font-medium text-slate-700">Page content (visual editor)</label>
                    <div class="mt-1 mb-2 flex flex-wrap gap-2 text-xs">
                        <span class="text-slate-500 mr-1">Add a ready‑made block:</span>
                        <button type="button" onclick="insertBlock('hero')" class="inline-flex items-center px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">Hero section</button>
                        <button type="button" onclick="insertBlock('features')" class="inline-flex items-center px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">Feature grid</button>
                        <button type="button" onclick="insertBlock('cta')" class="inline-flex items-center px-2.5 py-1 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">Call‑to‑action</button>
                    </div>
                    <textarea id="content" name="content" class="w-full rounded border-slate-300 text-sm"><?php echo htmlspecialchars($edit_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="mt-1 text-xs text-slate-500">Use the toolbar above the editor to add headings, paragraphs, images, buttons, and links. No coding needed.</p>

                    <?php if (!empty($parts) || !empty($snippets) || !empty($media_items)): ?>
                        <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-3 space-y-3">
                            <?php if (!empty($parts)): ?>
                                <div>
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Reusable parts</h3>
                                    <p class="mb-2 text-xs text-slate-500">Click a part to insert its HTML into this page at the current cursor position.</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($parts as $part): ?>
                                            <button type="button"
                                                    class="inline-flex items-center rounded border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100"
                                                    data-part-id="<?php echo htmlspecialchars($part['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-part-content="<?php echo htmlspecialchars($part['content'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="insertPart(this)">
                                                <?php echo htmlspecialchars($part['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-2 text-[11px] text-slate-400">Manage parts on the <a href="parts.php" class="text-blue-600 hover:underline">Reusable parts</a> screen.</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($snippets)): ?>
                                <div class="border-t border-slate-200 pt-3">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Code snippets</h3>
                                    <p class="mb-2 text-xs text-slate-500">Click a snippet to insert its code into this page at the current cursor position.</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($snippets as $snippet): ?>
                                            <button type="button"
                                                    class="inline-flex items-center rounded border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100"
                                                    data-snippet-id="<?php echo htmlspecialchars($snippet['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-snippet-content="<?php echo htmlspecialchars($snippet['code'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="insertSnippet(this)">
                                                <?php echo htmlspecialchars($snippet['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-2 text-[11px] text-slate-400">Manage snippets on the <a href="snippets.php" class="text-blue-600 hover:underline">Code snippets</a> screen.</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($media_items)): ?>
                                <div class="border-t border-slate-200 pt-3">
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Media library</h3>
                                    <p class="mb-2 text-xs text-slate-500">Click a media item to insert an appropriate HTML snippet (image, video, audio, or file link).</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($media_items as $media): ?>
                                            <?php
                                            $mediaUrl = 'data/media/' . rawurlencode($media['filename']);
                                            $mime = strtolower((string)($media['mime_type'] ?? ''));
                                            $label = $media['original_name'] ?? $media['filename'];
                                            $type = 'file';
                                            if (strpos($mime, 'image/') === 0) {
                                                $type = 'image';
                                            } elseif (strpos($mime, 'video/') === 0) {
                                                $type = 'video';
                                            } elseif (strpos($mime, 'audio/') === 0) {
                                                $type = 'audio';
                                            }
                                            ?>
                                            <button type="button"
                                                    class="inline-flex items-center rounded border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-100"
                                                    data-media-url="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-media-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-media-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="insertMedia(this)">
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-2 text-[11px] text-slate-400">Manage files on the <a href="media.php" class="text-blue-600 hover:underline">Media</a> screen.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="page_css_editor" class="block text-sm font-medium text-slate-700">Page CSS (optional)</label>
                        <textarea id="page_css_editor" rows="10"
                                  class="mt-1 block w-full rounded border-slate-300 font-mono text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($edit_css, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="mt-1 text-xs text-slate-500">CSS that only applies to this page. Saved as a static file for fast loading.</p>
                    </div>
                    <div>
                        <label for="page_js_editor" class="block text-sm font-medium text-slate-700">Page JavaScript (optional)</label>
                        <textarea id="page_js_editor" rows="10"
                                  class="mt-1 block w-full rounded border-slate-300 font-mono text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($edit_js, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="mt-1 text-xs text-slate-500">JavaScript that only runs on this page. Saved as a static file for fast loading.</p>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-700 mb-1">Live preview</h3>
                    <iframe id="previewFrame" class="w-full rounded border border-slate-200 bg-white" style="height: 900px;"></iframe>
                    <p class="mt-1 text-xs text-slate-500">This shows how your page will look as you type.</p>
                </div>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save page</button>
                <a href="admin.php" class="inline-flex items-center px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>

        <script>
            function insertBlock(type) {
                var blocks = {
                    hero: '<section style="padding:40px 20px;text-align:center;background:#f5f5f5;margin-bottom:30px;">' +
                        '<h1 style="font-size:32px;margin-bottom:10px;">Your big headline goes here</h1>' +
                        '<p style="font-size:16px;color:#555;margin-bottom:20px;">Use this area to quickly explain what your site or product is about.</p>' +
                        '<a href="#" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:4px;">Call to action</a>' +
                        '</section>',
                    features: '<section style="padding:30px 20px;margin-bottom:30px;">' +
                        '<h2 style="font-size:24px;margin-bottom:20px;text-align:center;">Features</h2>' +
                        '<div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">' +
                        '<div style="flex:1 1 220px;max-width:260px;border:1px solid #e5e7eb;padding:16px;border-radius:6px;">' +
                        '<h3 style="font-size:18px;margin-bottom:8px;">Feature one</h3>' +
                        '<p style="font-size:14px;color:#555;">Brief description of what makes this great.</p>' +
                        '</div>' +
                        '<div style="flex:1 1 220px;max-width:260px;border:1px solid #e5e7eb;padding:16px;border-radius:6px;">' +
                        '<h3 style="font-size:18px;margin-bottom:8px;">Feature two</h3>' +
                        '<p style="font-size:14px;color:#555;">Another benefit or key selling point.</p>' +
                        '</div>' +
                        '<div style="flex:1 1 220px;max-width:260px;border:1px solid #e5e7eb;padding:16px;border-radius:6px;">' +
                        '<h3 style="font-size:18px;margin-bottom:8px;">Feature three</h3>' +
                        '<p style="font-size:14px;color:#555;">Add more details about your offering.</p>' +
                        '</div>' +
                        '</div>' +
                        '</section>',
                    cta: '<section style="padding:30px 20px;margin:40px 0;background:#1f2937;color:#fff;text-align:center;border-radius:6px;">' +
                        '<h2 style="font-size:24px;margin-bottom:8px;">Ready to get started?</h2>' +
                        '<p style="font-size:15px;margin-bottom:16px;color:#e5e7eb;">Add a short message here that encourages visitors to take the next step.</p>' +
                        '<a href="#" style="display:inline-block;padding:10px 20px;background:#10b981;color:#fff;text-decoration:none;border-radius:4px;">Get in touch</a>' +
                        '</section>'
                };
                var html = blocks[type];
                if (!html) return;

                if (window.tinymce && tinymce.get('content')) {
                    tinymce.get('content').insertContent(html);
                } else {
                    var textarea = document.getElementById('content');
                    textarea.value += "\n\n" + html;
                }
                updatePreview();
            }

            function insertPart(button) {
                if (!button) return;
                var html = button.getAttribute('data-part-content') || '';
                if (!html) return;

                if (window.tinymce && tinymce.get('content')) {
                    tinymce.get('content').insertContent(html);
                } else {
                    var textarea = document.getElementById('content');
                    if (!textarea) return;
                    var start = textarea.selectionStart || textarea.value.length;
                    var end = textarea.selectionEnd || textarea.value.length;
                    var before = textarea.value.substring(0, start);
                    var after = textarea.value.substring(end);
                    textarea.value = before + html + after;
                }
                updatePreview();
            }

            function insertPart(button) {
                if (!button) return;
                var html = button.getAttribute('data-part-content') || '';
                if (!html) return;

                if (window.tinymce && tinymce.get('content')) {
                    tinymce.get('content').insertContent(html);
                } else {
                    var textarea = document.getElementById('content');
                    if (!textarea) return;
                    var start = textarea.selectionStart || textarea.value.length;
                    var end = textarea.selectionEnd || textarea.value.length;
                    var before = textarea.value.substring(0, start);
                    var after = textarea.value.substring(end);
                    textarea.value = before + html + after;
                }
                updatePreview();
            }

            function insertSnippet(button) {
                if (!button) return;
                var code = button.getAttribute('data-snippet-content') || '';
                if (!code) return;

                if (window.tinymce && tinymce.get('content')) {
                    tinymce.get('content').insertContent(code);
                } else {
                    var textarea = document.getElementById('content');
                    if (!textarea) return;
                    var start = textarea.selectionStart || textarea.value.length;
                    var end = textarea.selectionEnd || textarea.value.length;
                    var before = textarea.value.substring(0, start);
                    var after = textarea.value.substring(end);
                    textarea.value = before + code + after;
                }
                updatePreview();
            }

            function insertMedia(button) {
                if (!button) return;
                var url = button.getAttribute('data-media-url') || '';
                var type = button.getAttribute('data-media-type') || 'file';
                var label = button.getAttribute('data-media-label') || url;
                if (!url) return;

                var html = '';
                if (type === 'image') {
                    html = '<img src="' + url + '" alt="' + label.replace(/"/g, '&quot;') + '" style="max-width:100%;height:auto;" />';
                } else if (type === 'video') {
                    html = '<video controls src="' + url + '" style="max-width:100%;height:auto;"></video>';
                } else if (type === 'audio') {
                    html = '<audio controls src="' + url + '"></audio>';
                } else {
                    html = '<a href="' + url + '">' + label + '</a>';
                }

                if (window.tinymce && tinymce.get('content')) {
                    tinymce.get('content').insertContent(html);
                } else {
                    var textarea = document.getElementById('content');
                    if (!textarea) return;
                    var start = textarea.selectionStart || textarea.value.length;
                    var end = textarea.selectionEnd || textarea.value.length;
                    var before = textarea.value.substring(0, start);
                    var after = textarea.value.substring(end);
                    textarea.value = before + html + after;
                }
                updatePreview();
            }

            function updatePreview() {
                var frame = document.getElementById('previewFrame');
                var doc = frame.contentDocument || frame.contentWindow.document;
                var title = document.getElementById('title').value || 'Preview';
                var content;
                var css = '';
                var cssField = document.getElementById('page_css_editor');
                if (cssField) {
                    css = cssField.value || '';
                }
                if (window.tinymce && tinymce.get('content')) {
                    content = tinymce.get('content').getContent();
                } else {
                    content = document.getElementById('content').value;
                }
                doc.open();
                doc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' +
                    title.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                    '</title><style>body{font-family:Arial,sans-serif;padding:20px;}' +
                    css +
                    '</style></head><body>' +
                    content +
                    '</body></html>');
                doc.close();
            }

            document.getElementById('content').addEventListener('input', updatePreview);
            document.getElementById('title').addEventListener('input', updatePreview);

            window.addEventListener('load', updatePreview);

            if (window.tinymce) {
                tinymce.init({
                    selector: '#content',
                    height: 300,
                    menubar: true,
                    plugins: 'link lists code table',
                    toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright | bullist numlist | link | code',
                    // Be permissive so uploaded full HTML (including <style>, <link>, etc.) is preserved
                    verify_html: false,
                    valid_elements: '*[*]',
                    valid_children: '+body[style|link|script]',
                    forced_root_block: false,
                    setup: function (editor) {
                        editor.on('keyup change', updatePreview);
                        editor.on('init', updatePreview);
                    }
                });
            }

            // Convert CSS/JS textareas to file uploads before form submit.
            // File uploads bypass Cloudflare WAF (it doesn't inspect file contents).
            document.querySelector('form[action="admin.php"]').addEventListener('submit', function(e) {
                var cssText = document.getElementById('page_css_editor').value || '';
                var jsText = document.getElementById('page_js_editor').value || '';

                // Create File objects from textarea content
                var dt = new DataTransfer();
                dt.items.add(new File([cssText], 'page.css', {type: 'text/css'}));
                var cssInput = document.createElement('input');
                cssInput.type = 'file';
                cssInput.name = 'page_css_file';
                cssInput.style.display = 'none';
                cssInput.files = dt.files;
                this.appendChild(cssInput);

                var dt2 = new DataTransfer();
                dt2.items.add(new File([jsText], 'page.js', {type: 'text/javascript'}));
                var jsInput = document.createElement('input');
                jsInput.type = 'file';
                jsInput.name = 'page_js_file';
                jsInput.style.display = 'none';
                jsInput.files = dt2.files;
                this.appendChild(jsInput);
            });
        </script>
    <?php endif; ?>
</main>
<?php render_admin_sidebar_close(); ?>
</body>
</html>
