<?php
require __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pages = load_pages();
$auto_snippets = load_auto_include_snippets();

// Site-wide settings for title, logo, and navigation
$site_title = get_setting('site_title', 'Your Site');
$site_logo_url = get_setting('site_logo_url', '');
$site_custom_css = get_setting('site_custom_css', '');

// Global default menu (fallback)
$raw_menu_items = get_setting('site_menu_items', '[]');
$site_menu_items = [];
if ($raw_menu_items !== '') {
    $decoded_menu = json_decode($raw_menu_items, true);
    if (is_array($decoded_menu)) {
        $site_menu_items = $decoded_menu;
    }
}

// Home page-specific menu (overrides global on home)
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

$slug = $_GET['page'] ?? null;

// Decide which page to show (if any)
if ($slug !== null) {
    // Explicit URL slug: render that page
    $page = find_page_by_slug($pages, $slug);

    if (!$page) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Page Not Found</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding-top: 60px;
                }
                a {
                    color: #0066cc;
                    text-decoration: none;
                }
            <?php if ($site_custom_css !== ''): ?>
            <?php echo $site_custom_css; ?>
            <?php endif; ?>
            </style>
            <?php if (!empty($auto_snippets)): ?>
                <?php foreach ($auto_snippets as $snippet): ?>
                    <?php echo $snippet['code']; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </head>
        <body>
        <h1>404 - Page Not Found</h1>
        <p>The page you are looking for does not exist.</p>
        <p><a href="index.php">Back to home</a></p>
        </body>
        </html>
        <?php
        exit;
    }
} else {
    // No slug: try to show configured landing page, otherwise list pages
    $home_page_id = get_setting('home_page_id');
    $page = null;

    if ($home_page_id) {
        $page = find_page_by_id($pages, $home_page_id);
    }

    if (!$page) {
        // Fallback home page: list of pages
        // Choose menu: home-specific overrides global
        $home_menu_items = !empty($site_menu_home_items) ? $site_menu_home_items : $site_menu_items;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?> - Home</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    background: #f5f5f5;
                }
                header {
                    background: #333;
                    color: #fff;
                    padding: 10px 20px;
                }
                header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .container {
                    padding: 20px;
                }
                .page-list {
                    list-style: none;
                    padding: 0;
                }
                .page-list li {
                    margin-bottom: 5px;
                }
                a {
                    color: #0066cc;
                    text-decoration: none;
                }
                a:hover {
                    text-decoration: underline;
                }
                .admin-link {
                    float: right;
                    font-size: 14px;
                }
            <?php if ($site_custom_css !== ''): ?>
            <?php echo $site_custom_css; ?>
            <?php endif; ?>
            </style>
            <?php if (!empty($auto_snippets)): ?>
                <?php foreach ($auto_snippets as $snippet): ?>
                    <?php echo $snippet['code']; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </head>
        <body>
        <header id="main-menu-header">
            <h1 style="margin:0;font-size:22px;display:flex;align-items:center;justify-content:space-between;">
                <span>
                    <a href="index.php" style="color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                        <?php if ($site_logo_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($site_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?>"
                                 style="max-height:32px;display:inline-block;">
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                </span>
                <span id="main-menu" style="font-size:14px;">
                    <?php if (!empty($home_menu_items)): ?>
                        <?php foreach ($home_menu_items as $item): ?>
                            <a href="<?php echo htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"
                               style="color:#fff;text-decoration:none;margin-left:16px;">
                                <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($show_admin_link): ?>
                        <a href="admin.php" style="color:#fff;text-decoration:none;margin-left:16px;">Admin</a>
                    <?php endif; ?>
                </span>
            </h1>
        </header>
        <div id="page-body-home" class="container">
            <h2>Pages</h2>
            <?php if (empty($pages)): ?>
                <p>No pages published yet. <a href="admin.php">Go to admin to create one.</a></p>
            <?php else: ?>
                <ul class="page-list">
                    <?php foreach ($pages as $page_item): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($page_item['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($page_item['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// At this point $page is the page to render
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $template = $page['template'] ?? 'default';
    $is_full_width = !empty($page['full_width']);
    $page_show_title = !array_key_exists('show_title', $page) ? true : !empty($page['show_title']);
    $page_show_meta = !array_key_exists('show_meta', $page) ? true : !empty($page['show_meta']);
    ?>
    <style>
        <?php if ($template === 'dark'): ?>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #111;
            color: #eee;
        }
        header {
            background: #000;
            color: #fff;
            padding: 10px 20px;
        }
        header h1 {
            margin: 0;
            font-size: 22px;
        }
        .container {
            padding: 0px;
            background: #222;
            margin: 0 auto;
            <?php if (!$is_full_width): ?>
            max-width: 960px;
            <?php else: ?>
            max-width: 100% !important;
            <?php endif; ?>
        }
        .meta {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 15px;
        }
        .content {
            line-height: 1.6;
        }
        .admin-link {
            float: right;
            font-size: 14px;
        }
        a {
            color: #66aaff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        <?php elseif ($template === 'minimal'): ?>
        body {
            font-family: Georgia, serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #222;
        }
        header {
            display: none;
        }
        .container {
            margin: 0;
            padding: 0;
        }
        .meta {
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
        }
        .content {
            line-height: 1.7;
        }
        .admin-link {
            float: right;
            font-size: 14px;
        }
        a {
            color: #333;
            text-decoration: underline;
        }
        a:hover {
            text-decoration: none;
        }
        <?php else: ?>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        header {
            background: #333;
            color: #fff;
            padding: 10px 20px;
        }
        header h1 {
            margin: 0;
            font-size: 22px;
        }
        .container {
            padding: 0px;
            background: #fff;
            margin: 0 auto;
            <?php if (!$is_full_width): ?>
            max-width: 960px;
            <?php else: ?>
            max-width: 100% !important;
            <?php endif; ?>
        }
        .meta {
            font-size: 12px;
            color: #777;
            margin-bottom: 15px;
        }
        .content {
            line-height: 1.6;
        }
        .admin-link {
            float: right;
            font-size: 14px;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        <?php endif; ?>
        <?php if ($is_full_width): ?>
        .content .container {
            max-width: 100% !important;
        }
        <?php endif; ?>
        <?php if ($site_custom_css !== ''): ?>
        <?php echo $site_custom_css; ?>
        <?php endif; ?>
    </style>
    <?php if (!empty($auto_snippets)): ?>
        <?php foreach ($auto_snippets as $snippet): ?>
            <?php echo $snippet['code']; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<header id="main-menu-header">
    <h1 style="margin:0;font-size:22px;display:flex;align-items:center;justify-content:space-between;">
        <span>
            <a href="index.php" style="color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                <?php if ($site_logo_url !== ''): ?>
                    <img src="<?php echo htmlspecialchars($site_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?>"
                         style="max-height:28px;display:inline-block;">
                <?php endif; ?>
                <span><?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        </span>
        <span id="main-menu" class="admin-link" style="font-size:14px;">
            <?php
            // Choose menu based on template for inner pages
            $page_menu_items = $site_menu_items;
            if ($template === 'dark' && !empty($site_menu_page_dark_items)) {
                $page_menu_items = $site_menu_page_dark_items;
            } elseif ($template === 'minimal' && !empty($site_menu_page_minimal_items)) {
                $page_menu_items = $site_menu_page_minimal_items;
            } elseif ($template === 'default' && !empty($site_menu_page_default_items)) {
                $page_menu_items = $site_menu_page_default_items;
            }
            ?>
            <?php if (!empty($page_menu_items)): ?>
                <?php foreach ($page_menu_items as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"
                       style="color:#fff;text-decoration:none;margin-left:16px;">
                        <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($show_admin_link): ?>
                <a href="admin.php" style="color:#fff;text-decoration:none;margin-left:16px;">Admin</a>
            <?php endif; ?>
        </span>
    </h1>
</header>
<div id="page-body" class="container">
    <?php if ($page_show_title): ?>
        <h2><?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php endif; ?>
    <?php if ($page_show_meta): ?>
        <div class="meta">
            URL slug: <code><?php echo htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8'); ?></code>
            <?php if (!empty($page['updated_at'])): ?>
                | Last updated: <?php echo htmlspecialchars($page['updated_at'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="content">
        <?php echo render_content_with_products($page['content']); ?>
    </div>
</div>
<script>
(function(){
    var slot = document.getElementById('csrf-slot');
    if (slot) {
        slot.innerHTML = '<input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>">';
    }
})();
</script>
</body>
</html>
