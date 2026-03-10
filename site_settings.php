<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);

// Site-wide appearance and navigation settings
$site_title = get_setting('site_title', 'Your Site');
$site_logo_url = get_setting('site_logo_url', '');
$site_custom_css = get_setting('site_custom_css', '');

// Global default menu (fallback)
$raw_menu_items = get_setting('site_menu_items', '[]');
$site_menu_items = [];
if ($raw_menu_items !== '') {
    $decoded = json_decode($raw_menu_items, true);
    if (is_array($decoded)) {
        $site_menu_items = $decoded;
    }
}

// Home page-specific menu
$raw_menu_home = get_setting('site_menu_home_items', '');
$site_menu_home_items = [];
if ($raw_menu_home !== '') {
    $decoded_home = json_decode($raw_menu_home, true);
    if (is_array($decoded_home)) {
        $site_menu_home_items = $decoded_home;
    }
}

// Per-template menus for inner pages
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_title = trim($_POST['site_title'] ?? '');
    $new_logo_url = trim($_POST['site_logo_url'] ?? '');
    $new_show_admin_link = !empty($_POST['show_admin_link']) ? '1' : '0';
    $new_site_css = $_POST['site_custom_css'] ?? '';

    if ($new_title === '') {
        $new_title = 'Your Site';
    }

    set_setting('site_title', $new_title);
    set_setting('site_logo_url', $new_logo_url !== '' ? $new_logo_url : null);
    set_setting('show_admin_link', $new_show_admin_link);
    set_setting('site_custom_css', $new_site_css !== '' ? $new_site_css : null);

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

    header('Location: site_settings.php?site_saved=1');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site appearance & navigation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Site appearance &amp; navigation</h1>
        <div class="space-x-2 text-sm">
            <a href="admin.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Pages dashboard</a>
            <a href="parts.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Reusable parts</a>
            <a href="snippets.php" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">Code snippets</a>
            <a href="index.php" target="_blank" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600">View site</a>
            <a href="login.php?logout=1" class="inline-flex items-center px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700">Logout</a>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6">
    <?php if (isset($_GET['site_saved'])): ?>
        <div class="mb-4 rounded border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm text-indigo-800">Site settings updated.</div>
    <?php endif; ?>

    <section class="rounded border border-slate-200 bg-white p-4">
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Site appearance &amp; navigation</h2>
        <p class="mb-3 text-xs text-slate-500">Configure your site name, optional logo, main menu items, and whether the public header shows an Admin link.</p>
        <form method="post" action="site_settings.php" class="space-y-3">
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label for="site_title" class="block text-sm font-medium text-slate-700">Site title</label>
                    <input type="text" id="site_title" name="site_title"
                           class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                           value="<?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="mt-1 text-xs text-slate-500">Shown in the header next to the logo (if any) and in the browser title for some pages.</p>
                </div>
                <div>
                    <label for="site_logo_url" class="block text-sm font-medium text-slate-700">Logo image URL (optional)</label>
                    <input type="text" id="site_logo_url" name="site_logo_url"
                           class="mt-1 block w-full rounded border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                           placeholder="https://example.com/logo.png"
                           value="<?php echo htmlspecialchars($site_logo_url, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="mt-1 text-xs text-slate-500">If set, the logo is shown in the header; the site title becomes a text label beside it.</p>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <div class="pt-1">
                    <input id="show_admin_link" name="show_admin_link" type="checkbox"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        <?php echo $show_admin_link ? 'checked' : ''; ?>>
                </div>
                <div>
                    <label for="show_admin_link" class="text-sm font-medium text-slate-700">Show “Admin” link in public header</label>
                    <p class="mt-0.5 text-xs text-slate-500">Uncheck this if you want to hide the admin link from visitors. You can still reach the admin by typing the URL directly.</p>
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label for="site_custom_css" class="block text-sm font-medium text-slate-700">Global site CSS (optional)</label>
                    <textarea id="site_custom_css" name="site_custom_css" rows="6"
                              class="mt-1 block w-full rounded border-slate-300 font-mono text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($site_custom_css, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="mt-1 text-xs text-slate-500">CSS entered here is added on every page and is ideal for adjusting your header, navigation, or other global elements.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-slate-700">Global default menu</label>
                        <span class="text-[11px] text-slate-400">Used when no specific menu is set for a page type.</span>
                    </div>
                    <div class="overflow-hidden rounded border border-slate-200">
                        <table class="min-w-full text-xs bg-slate-50/50">
                            <thead>
                            <tr class="bg-slate-50 text-left text-slate-700">
                                <th class="px-3 py-1.5 font-medium w-1/3">Label</th>
                                <th class="px-3 py-1.5 font-medium">Link URL</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $maxMenuItems = 5;
                            for ($i = 0; $i < $maxMenuItems; $i++):
                                $label = $site_menu_items[$i]['label'] ?? '';
                                $url = $site_menu_items[$i]['url'] ?? '';
                                ?>
                                <tr class="border-t border-slate-100 bg-white">
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="menu_label[<?php echo $i; ?>]"
                                               class="block w-full rounded border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="e.g. About"
                                               value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="menu_url[<?php echo $i; ?>]"
                                               class="block w-full rounded border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="e.g. index.php?page=about"
                                               value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-slate-700">Home page menu</label>
                        <span class="text-[11px] text-slate-400">Overrides the global menu on the home page.</span>
                    </div>
                    <div class="overflow-hidden rounded border border-slate-200">
                        <table class="min-w-full text-xs bg-slate-50/50">
                            <thead>
                            <tr class="bg-slate-50 text-left text-slate-700">
                                <th class="px-3 py-1.5 font-medium w-1/3">Label</th>
                                <th class="px-3 py-1.5 font-medium">Link URL</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            for ($i = 0; $i < $maxMenuItems; $i++):
                                $label = $site_menu_home_items[$i]['label'] ?? '';
                                $url = $site_menu_home_items[$i]['url'] ?? '';
                                ?>
                                <tr class="border-t border-slate-100 bg-white">
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="menu_home_label[<?php echo $i; ?>]"
                                               class="block w-full rounded border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="e.g. Home"
                                               value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="menu_home_url[<?php echo $i; ?>]"
                                               class="block w-full rounded border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="e.g. index.php"
                                               value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="mb-1">
                        <span class="text-sm font-medium text-slate-700">Per-template menus for pages</span>
                        <p class="text-[11px] text-slate-400">These override the global menu on pages using the matching template.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="overflow-hidden rounded border border-slate-200 bg-white">
                            <div class="px-3 py-1.5 border-b border-slate-100 text-xs font-semibold text-slate-700">Default template</div>
                            <table class="min-w-full text-[11px]">
                                <tbody>
                                <?php
                                for ($i = 0; $i < $maxMenuItems; $i++):
                                    $label = $site_menu_page_default_items[$i]['label'] ?? '';
                                    $url = $site_menu_page_default_items[$i]['url'] ?? '';
                                    ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_default_label[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="Label"
                                                   value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_default_url[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="URL"
                                                   value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="overflow-hidden rounded border border-slate-200 bg-white">
                            <div class="px-3 py-1.5 border-b border-slate-100 text-xs font-semibold text-slate-700">Dark template</div>
                            <table class="min-w-full text-[11px]">
                                <tbody>
                                <?php
                                for ($i = 0; $i < $maxMenuItems; $i++):
                                    $label = $site_menu_page_dark_items[$i]['label'] ?? '';
                                    $url = $site_menu_page_dark_items[$i]['url'] ?? '';
                                    ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_dark_label[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="Label"
                                                   value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_dark_url[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="URL"
                                                   value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="overflow-hidden rounded border border-slate-200 bg-white">
                            <div class="px-3 py-1.5 border-b border-slate-100 text-xs font-semibold text-slate-700">Minimal template</div>
                            <table class="min-w-full text-[11px]">
                                <tbody>
                                <?php
                                for ($i = 0; $i < $maxMenuItems; $i++):
                                    $label = $site_menu_page_minimal_items[$i]['label'] ?? '';
                                    $url = $site_menu_page_minimal_items[$i]['url'] ?? '';
                                    ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_minimal_label[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="Label"
                                                   value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <td class="px-2 py-1">
                                            <input type="text" name="menu_minimal_url[<?php echo $i; ?>]"
                                                   class="block w-full rounded border-slate-200 text-[11px] focus:border-blue-500 focus:ring-blue-500"
                                                   placeholder="URL"
                                                   value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-1 flex justify-end">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium">Save site settings</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
