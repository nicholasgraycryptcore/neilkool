<?php
require __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$product = $slug !== '' ? find_product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    echo '<h1>Product not found</h1>';
    exit;
}

$categories = load_categories();
$categoryLookup = [];
foreach ($categories as $cat) {
    $categoryLookup[$cat['id']] = $cat['name'];
}

$primaryImage = product_primary_image_url($product);
$gallery = product_gallery_urls($product);
$categoryLabel = '';
if (!empty($product['category_id']) && isset($categoryLookup[$product['category_id']])) {
    $categoryLabel = $categoryLookup[$product['category_id']];
}
if (!empty($product['subcategory_id']) && isset($categoryLookup[$product['subcategory_id']])) {
    $categoryLabel = $categoryLabel !== '' ? $categoryLabel . ' / ' . $categoryLookup[$product['subcategory_id']] : $categoryLookup[$product['subcategory_id']];
}

// Try to load editable page content generated for this product
$pageSlug = 'product-' . $product['slug'];
$pageContent = '';
$pages = load_pages();
$page = find_page_by_slug($pages, $pageSlug);
if ($page) {
    $pageContent = render_content_with_products($page['content']);
}

function product_money_display(array $product): string
{
    return ($product['currency'] ?? 'USD') . ' ' . number_format(((int)$product['price_cents']) / 100, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<header class="bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="text-sm space-x-3">
            <a href="shop.php" class="underline">Shop</a>
            <a href="admin.php" class="underline">Admin</a>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6 grid md:grid-cols-2 gap-8">
    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-4">
        <?php if ($primaryImage): ?>
            <img src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-slate-200">
        <?php endif; ?>
        <?php if (!empty($gallery)): ?>
            <div class="grid grid-cols-3 gap-2">
                <?php foreach ($gallery as $img): ?>
                    <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-24 object-cover rounded border border-slate-200">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="bg-white border border-slate-200 rounded shadow-sm p-4 space-y-4">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if ($categoryLabel !== ''): ?>
                <div class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
        <div class="text-lg font-bold text-emerald-700"><?php echo htmlspecialchars(product_money_display($product), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
        <div class="flex items-center gap-2 text-sm">
            <span class="font-medium">Stock:</span>
            <span><?php echo (int)$product['stock'] > 0 ? (int)$product['stock'] : 'Out of stock'; ?></span>
        </div>
        <form method="post" action="shop.php" class="flex items-center gap-3">
            <input type="hidden" name="form_action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'product.php?slug=' . $product['slug'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="number" name="quantity" value="1" min="1" class="border rounded px-3 py-2 text-sm w-24">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Add to cart</button>
        </form>
    </section>
</main>

<section class="max-w-6xl mx-auto px-4 pb-10">
    <?php if ($pageContent !== ''): ?>
        <div class="bg-white border border-slate-200 rounded shadow-sm p-6">
            <?php echo $pageContent; ?>
        </div>
    <?php endif; ?>
</section>
</body>
</html>
