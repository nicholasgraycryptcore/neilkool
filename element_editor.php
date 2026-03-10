<?php
require __DIR__ . '/auth.php';
require_login();

$pages = load_pages();
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: admin.php');
    exit;
}

$page = find_page_by_id($pages, $id);
if (!$page) {
    http_response_code(404);
    echo "Page not found. <a href=\"admin.php\">Back to admin</a>";
    exit;
}

function get_editable_nodes(DOMDocument $dom): array
{
    $xpath = new DOMXPath($dom);
    $nodes = [];
    // Treat common text/heading/link tags as editable
    $nodeList = $xpath->query('//*[self::p or self::a or self::span or self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::b or self::strong or self::em or self::i]');
    foreach ($nodeList as $node) {
        $nodes[] = $node;
    }
    return $nodes;
}

// Handle save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $html = $page['content'] ?? '';
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $nodes = get_editable_nodes($dom);

    $submitted = $_POST['elem'] ?? [];
    foreach ($nodes as $index => $node) {
        if (!isset($submitted[$index])) {
            continue;
        }
        $data = $submitted[$index];
        $text = $data['text'] ?? '';
        $node->nodeValue = $text;

        if ($node->nodeName === 'a' && isset($data['href'])) {
            $href = trim($data['href']);
            if ($href === '') {
                $node->removeAttribute('href');
            } else {
                $node->setAttribute('href', $href);
            }
        }
    }

    $newHtml = $dom->saveHTML();

    foreach ($pages as $i => $p) {
        if ($p['id'] === $page['id']) {
            $pages[$i]['content'] = $newHtml;
            $pages[$i]['updated_at'] = date('c');
            $page = $pages[$i];
            break;
        }
    }
    save_pages($pages);

    header('Location: element_editor.php?id=' . urlencode($page['id']) . '&saved=1');
    exit;
}

// Prepare nodes for display
$html = $page['content'] ?? '';
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$nodes = get_editable_nodes($dom);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Section Editor - <?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<header class="bg-slate-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Quick text editor</h1>
        <div class="text-xs text-slate-200">Page: <?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6">
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="admin.php?action=edit&id=<?php echo urlencode($page['id']); ?>"
           class="inline-flex items-center px-3 py-1.5 rounded border border-slate-300 text-slate-700 hover:bg-slate-50">Back to page editor</a>
        <a href="<?php echo htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"
           class="inline-flex items-center px-3 py-1.5 rounded bg-slate-700 text-white hover:bg-slate-600">View page</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Content sections saved.</div>
    <?php endif; ?>

    <?php if (empty($nodes)): ?>
        <p class="text-sm text-slate-600 bg-white border rounded px-4 py-3">No editable text elements (&lt;h1&gt;-&lt;h6&gt;, &lt;p&gt;, &lt;a&gt;, &lt;span&gt;, &lt;b&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;i&gt;) were found in this page.</p>
    <?php else: ?>
        <p class="mb-3 text-sm text-slate-600">Use this screen to update text and links without touching any HTML. Each row represents a heading or text element (paragraphs, links, spans, bold/italic text) on your page.</p>
        <form method="post" action="element_editor.php?id=<?php echo urlencode($page['id']); ?>" class="space-y-3">
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-50 text-left text-slate-700">
                        <th class="px-3 py-2 font-medium w-10">#</th>
                        <th class="px-3 py-2 font-medium w-24">Type</th>
                        <th class="px-3 py-2 font-medium">Text</th>
                        <th class="px-3 py-2 font-medium w-64">Link URL (for links)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nodes as $index => $node): ?>
                        <?php
                        $tag = $node->nodeName;
                        $text = $node->textContent;
                        $href = $tag === 'a' ? $node->getAttribute('href') : '';
                        ?>
                        <tr class="border-t border-slate-100 align-top">
                            <td class="px-3 py-2 text-xs text-slate-500"><?php echo $index + 1; ?></td>
                            <td class="px-3 py-2 text-xs text-slate-600">
                                <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[10px] uppercase tracking-wide"> &lt;<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>&gt; </span>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       name="elem[<?php echo $index; ?>][text]"
                                       class="block w-full rounded border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                       value="<?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="mt-1 text-[11px] text-slate-400">Current text shown on the page.</div>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($tag === 'a'): ?>
                                    <input type="text"
                                           name="elem[<?php echo $index; ?>][href]"
                                           placeholder="https://example.com"
                                           class="block w-full rounded border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                           value="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mt-1 text-[11px] text-slate-400">Where this link should go.</div>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400">Not a link.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pt-2 flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">Save changes</button>
                <a href="admin.php?action=edit&id=<?php echo urlencode($page['id']); ?>"
                   class="inline-flex items-center px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
