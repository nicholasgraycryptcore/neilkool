<?php
require __DIR__ . '/auth.php';
require_login();
require_role(['admin']);
require __DIR__ . '/admin_nav.php';

// Handle flash messaging via query params for simplicity
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

// Helpers
function format_money(int $cents, string $currency = 'USD'): string
{
    return ($cents >= 0 ? '' : '-') . $currency . ' ' . number_format(abs($cents) / 100, 2);
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_category') {
        $name = trim($_POST['name'] ?? '');
        $parent_id = trim($_POST['parent_id'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($name === '') {
            header('Location: ecommerce.php?err=' . rawurlencode('Category name is required.'));
            exit;
        }

        try {
            save_category([
                'id' => $_POST['id'] ?? null,
                'name' => $name,
                'slug' => $slug !== '' ? slugify($slug) : null,
                'parent_id' => $parent_id !== '' ? $parent_id : null,
            ]);
            log_action('save_category', 'admin', 'category', $_POST['id'] ?? null, ['name' => $name]);
            header('Location: ecommerce.php?msg=' . rawurlencode('Category saved.'));
            exit;
        } catch (Throwable $e) {
            header('Location: ecommerce.php?err=' . rawurlencode('Could not save category: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'save_product') {
        $name = trim($_POST['name'] ?? '');
        $price_raw = trim($_POST['price'] ?? '0');
        $price_cents = (int)round((float)$price_raw * 100);
        $stock = (int)($_POST['stock'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'USD');
        $status = trim($_POST['status'] ?? 'active');

        if ($name === '') {
            header('Location: ecommerce.php?err=' . rawurlencode('Product name is required.'));
            exit;
        }

        try {
            $existingId = $_POST['id'] ?? null;
            $productId = save_product([
                'id' => $existingId,
                'name' => $name,
                'slug' => trim($_POST['slug'] ?? '') ?: null,
                'description' => trim($_POST['description'] ?? ''),
                'price_cents' => $price_cents,
                'currency' => $currency !== '' ? $currency : 'USD',
                'sku' => trim($_POST['sku'] ?? '') ?: null,
                'category_id' => trim($_POST['category_id'] ?? '') ?: null,
                'subcategory_id' => trim($_POST['subcategory_id'] ?? '') ?: null,
                'stock' => $stock,
                'status' => $status !== '' ? $status : 'active',
                'image_id' => trim($_POST['image_id'] ?? '') ?: null,
                'primary_image_url' => trim($_POST['primary_image_url'] ?? '') ?: null,
                'gallery_images' => trim($_POST['gallery_images'] ?? ''),
                'show_price' => isset($_POST['show_price']) ? 1 : 0,
            ]);
            ensure_product_page($productId);
            log_action('save_product', 'admin', 'product', $productId, ['name' => $name, 'price_cents' => $price_cents]);
            header('Location: ecommerce.php?msg=' . rawurlencode('Product saved.'));
            exit;
        } catch (Throwable $e) {
            header('Location: ecommerce.php?err=' . rawurlencode('Could not save product: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'save_menu_item') {
        $label = trim($_POST['menu_label'] ?? '');
        $url = trim($_POST['menu_url'] ?? '');
        $sort_order = (int)($_POST['menu_sort_order'] ?? 0);
        $open_new_tab = isset($_POST['menu_open_new_tab']) ? 1 : 0;

        if ($label === '' || $url === '') {
            header('Location: ecommerce.php?err=' . rawurlencode('Menu item label and URL are required.'));
            exit;
        }

        try {
            save_shop_menu_item([
                'id' => $_POST['menu_id'] ?? null,
                'label' => $label,
                'url' => $url,
                'sort_order' => $sort_order,
                'open_new_tab' => $open_new_tab,
            ]);
            log_action('save_shop_menu_item', 'admin', 'shop_menu', $_POST['menu_id'] ?? null, ['label' => $label]);
            header('Location: ecommerce.php?msg=' . rawurlencode('Menu item saved.'));
            exit;
        } catch (Throwable $e) {
            header('Location: ecommerce.php?err=' . rawurlencode('Could not save menu item: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'delete_menu_item') {
        $menu_id = trim($_POST['menu_id'] ?? '');
        if ($menu_id === '') {
            header('Location: ecommerce.php?err=' . rawurlencode('No menu item specified.'));
            exit;
        }
        try {
            delete_shop_menu_item($menu_id);
            log_action('delete_shop_menu_item', 'admin', 'shop_menu', $menu_id);
            header('Location: ecommerce.php?msg=' . rawurlencode('Menu item removed.'));
            exit;
        } catch (Throwable $e) {
            header('Location: ecommerce.php?err=' . rawurlencode('Could not delete menu item: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'adjust_stock') {
        $product_id = trim($_POST['product_id'] ?? '');
        $change_qty = (int)($_POST['change_qty'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'manual');

        if ($product_id === '' || $change_qty === 0) {
            header('Location: ecommerce.php?err=' . rawurlencode('Choose a product and enter a non-zero quantity.'));
            exit;
        }

        try {
            $newStock = adjust_inventory($product_id, $change_qty, $reason !== '' ? $reason : 'manual', 'admin');
            log_action('adjust_inventory', 'admin', 'product', $product_id, ['change_qty' => $change_qty, 'reason' => $reason, 'new_stock' => $newStock]);
            header('Location: ecommerce.php?msg=' . rawurlencode('Inventory updated. New stock: ' . $newStock));
            exit;
        } catch (Throwable $e) {
            header('Location: ecommerce.php?err=' . rawurlencode('Stock adjustment failed: ' . $e->getMessage()));
            exit;
        }
    }
}

$categories = load_categories();
$products_raw = load_products();
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $products_raw = array_values(array_filter($products_raw, function ($p) use ($search) {
        return stripos($p['name'], $search) !== false || stripos($p['sku'] ?? '', $search) !== false;
    }));
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$totalProducts = count($products_raw);
$products = array_slice($products_raw, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$category_lookup = [];
foreach ($categories as $cat) {
    $category_lookup[$cat['id']] = $cat['name'];
}
$media_items = load_media();
$shop_menu_items = load_shop_menu_items();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ecommerce Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<?php render_admin_sidebar('ecommerce'); ?>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <?php if ($message !== ''): ?>
        <div class="rounded bg-emerald-100 text-emerald-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rounded bg-rose-100 text-rose-800 px-4 py-2 text-sm"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-slate-800">Categories</h2>
        </div>
        <form method="post" class="grid md:grid-cols-4 gap-3">
            <input type="hidden" name="form_action" value="save_category">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2 text-sm" placeholder="Category name" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Parent (optional)</label>
                <select name="parent_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Save category</button>
            </div>
        </form>
        <?php if (!empty($categories)): ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Slug</th>
                        <th class="py-2 pr-4">Parent</th>
                        <th class="py-2">Updated</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo $cat['parent_id'] && isset($category_lookup[$cat['parent_id']]) ? htmlspecialchars($category_lookup[$cat['parent_id']], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td class="py-2 text-slate-600"><?php echo htmlspecialchars(substr((string)$cat['updated_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="mt-2 text-sm text-slate-600">No categories yet.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800">Products</h2>
        </div>
        <form method="get" class="flex items-center gap-2 mb-3">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name or SKU" class="border rounded px-3 py-2 text-sm w-64">
            <button type="submit" class="inline-flex items-center px-3 py-2 rounded bg-slate-200 text-slate-700 text-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="ecommerce.php" class="text-sm text-blue-600">Clear</a>
            <?php endif; ?>
        </form>
        <form method="post" id="product-form" class="grid md:grid-cols-3 gap-3">
            <input type="hidden" name="form_action" value="save_product">
            <input type="hidden" name="id" id="product_id" value="">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" id="product_name" class="w-full border rounded px-3 py-2 text-sm" placeholder="Product name" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">SKU</label>
                <input type="text" name="sku" id="product_sku" class="w-full border rounded px-3 py-2 text-sm" placeholder="Optional SKU">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                <input type="text" name="slug" id="product_slug" class="w-full border rounded px-3 py-2 text-sm" placeholder="Optional URL slug">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Price</label>
                <input type="number" step="0.01" min="0" name="price" id="product_price" class="w-full border rounded px-3 py-2 text-sm" placeholder="0.00" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                <input type="text" name="currency" id="product_currency" value="USD" class="w-full border rounded px-3 py-2 text-sm" maxlength="3">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Initial stock</label>
                <input type="number" name="stock" id="product_stock" class="w-full border rounded px-3 py-2 text-sm" value="0">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <select name="category_id" id="product_category_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subcategory</label>
                <select name="subcategory_id" id="product_subcategory_id" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status" id="product_status" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="show_price" value="1" id="product_show_price" checked class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-700">Show price on store</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Primary image URL</label>
                <input type="url" id="primary_image_url" name="primary_image_url" class="w-full border rounded px-3 py-2 text-sm" placeholder="https://.../main.jpg">
                <div class="mt-1"><img id="primary_image_preview" src="" alt="" class="h-16 w-16 object-cover rounded border border-slate-200 hidden"></div>
                <?php if (!empty($media_items)): ?>
                    <div class="mt-1 flex items-center gap-2">
                        <select id="primary_image_select" class="border rounded px-2 py-1 text-sm">
                            <option value="">Select from media</option>
                            <?php foreach ($media_items as $m): ?>
                                <?php $url = 'data/media/' . $m['filename']; ?>
                                <option value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($m['original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="primary_image_use" class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700">Use</button>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Gallery image URLs</label>
                <textarea name="gallery_images" id="gallery_images" rows="2" class="w-full border rounded px-3 py-2 text-sm" placeholder="One or comma-separated URLs"></textarea>
                <div id="gallery_preview" class="mt-2 flex flex-wrap gap-2"></div>
                <p class="text-xs text-slate-500 mt-1">Use uploaded media URLs; comma-separated for multiple images.</p>
                <?php if (!empty($media_items)): ?>
                    <div class="mt-1 flex items-center gap-2">
                        <select id="gallery_image_select" class="border rounded px-2 py-1 text-sm">
                            <option value="">Select from media</option>
                            <?php foreach ($media_items as $m): ?>
                                <?php $url = 'data/media/' . $m['filename']; ?>
                                <option value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($m['original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="gallery_image_add" class="text-xs px-2 py-1 rounded bg-slate-200 text-slate-700">Add to gallery</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" id="product_description" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Short description"></textarea>
            </div>
            <div class="md:col-span-3 flex justify-end gap-2">
                <button type="button" id="cancel-edit-btn" onclick="cancelProductEdit()" class="hidden inline-flex items-center px-4 py-2 rounded bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm">Cancel edit</button>
                <button type="submit" id="save-product-btn" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Save product</button>
            </div>
        </form>

        <?php if (!empty($products)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Product</th>
                        <th class="py-2 pr-4">Main image</th>
                        <th class="py-2 pr-4">Price</th>
                        <th class="py-2 pr-4">Stock</th>
                        <th class="py-2 pr-4">Category</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Updated</th>
                        <th class="py-2">Links</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $prod): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4">
                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-xs text-slate-500">SKU: <?php echo htmlspecialchars($prod['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td class="py-2 pr-4">
                                <?php if (!empty($prod['primary_image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($prod['primary_image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Primary" class="h-12 w-12 object-cover rounded border border-slate-200">
                                <?php else: ?>
                                    <span class="text-xs text-slate-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 pr-4 text-slate-800"><?php echo htmlspecialchars(format_money((int)$prod['price_cents'], $prod['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4"><?php echo (int)$prod['stock']; ?></td>
                            <td class="py-2 pr-4 text-slate-600">
                                <?php
                                $catLabel = $prod['category_id'] && isset($category_lookup[$prod['category_id']]) ? $category_lookup[$prod['category_id']] : '—';
                                $subLabel = $prod['subcategory_id'] && isset($category_lookup[$prod['subcategory_id']]) ? $category_lookup[$prod['subcategory_id']] : null;
                                echo htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8');
                                if ($subLabel) {
                                    echo ' / ' . htmlspecialchars($subLabel, ENT_QUOTES, 'UTF-8');
                                }
                                ?>
                            </td>
                            <td class="py-2 pr-4">
                                <span class="inline-flex px-2 py-1 rounded text-xs <?php echo $prod['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'; ?>">
                                    <?php echo htmlspecialchars($prod['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo htmlspecialchars(substr((string)$prod['updated_at'], 0, 19), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 whitespace-nowrap">
                                <button type="button" onclick='editProduct(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, "UTF-8"); ?>)' class="text-xs text-amber-600 hover:underline mr-2">Edit</button>
                                <a href="product-<?php echo htmlspecialchars($prod['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="text-xs text-blue-600 hover:underline">View</a>
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
                            <a href="ecommerce.php?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="ecommerce.php?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded border border-slate-200">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-600">No products yet.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-slate-800">Inventory adjustment</h2>
        </div>
        <form method="post" class="grid md:grid-cols-4 gap-3">
            <input type="hidden" name="form_action" value="adjust_stock">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Product</label>
                <select name="product_id" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?php echo htmlspecialchars($prod['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8'); ?> (Stock: <?php echo (int)$prod['stock']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Quantity change</label>
                <input type="number" name="change_qty" class="w-full border rounded px-3 py-2 text-sm" placeholder="+/-" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                <input type="text" name="reason" class="w-full border rounded px-3 py-2 text-sm" value="manual">
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Adjust stock</button>
            </div>
        </form>
    </section>

    <!-- Shop Menu Management -->
    <section class="bg-white rounded shadow-sm border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-slate-800">Shop Navigation Menu</h2>
        </div>
        <p class="text-sm text-slate-500 mb-3">Add links that appear in the shop page header navigation.</p>
        <form method="post" id="menu-item-form" class="grid md:grid-cols-5 gap-3">
            <input type="hidden" name="form_action" value="save_menu_item">
            <input type="hidden" name="menu_id" id="menu_item_id" value="">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Label</label>
                <input type="text" name="menu_label" id="menu_item_label" class="w-full border rounded px-3 py-2 text-sm" placeholder="e.g. Home, About, Contact" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">URL</label>
                <input type="text" name="menu_url" id="menu_item_url" class="w-full border rounded px-3 py-2 text-sm" placeholder="e.g. /about or https://example.com" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Sort order</label>
                <input type="number" name="menu_sort_order" id="menu_item_sort" class="w-full border rounded px-3 py-2 text-sm" value="0">
            </div>
            <div class="flex items-end gap-3 md:col-span-5">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="menu_open_new_tab" value="1" id="menu_item_new_tab" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-700">Open in new tab</span>
                </label>
                <div class="ml-auto flex gap-2">
                    <button type="button" id="cancel-menu-edit-btn" onclick="cancelMenuEdit()" class="hidden inline-flex items-center px-4 py-2 rounded bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm">Cancel</button>
                    <button type="submit" id="save-menu-btn" class="inline-flex items-center px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-sm">Add menu item</button>
                </div>
            </div>
        </form>
        <?php if (!empty($shop_menu_items)): ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-600 border-b">
                        <th class="py-2 pr-4">Order</th>
                        <th class="py-2 pr-4">Label</th>
                        <th class="py-2 pr-4">URL</th>
                        <th class="py-2 pr-4">New tab</th>
                        <th class="py-2">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shop_menu_items as $mi): ?>
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-4 text-slate-600"><?php echo (int)$mi['sort_order']; ?></td>
                            <td class="py-2 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($mi['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600 truncate max-w-xs"><?php echo htmlspecialchars($mi['url'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-2 pr-4 text-slate-600"><?php echo (int)$mi['open_new_tab'] ? 'Yes' : 'No'; ?></td>
                            <td class="py-2 whitespace-nowrap">
                                <button type="button" onclick='editMenuItem(<?php echo htmlspecialchars(json_encode($mi), ENT_QUOTES, "UTF-8"); ?>)' class="text-xs text-amber-600 hover:underline mr-2">Edit</button>
                                <form method="post" class="inline" onsubmit="return confirm('Remove this menu item?');">
                                    <input type="hidden" name="form_action" value="delete_menu_item">
                                    <input type="hidden" name="menu_id" value="<?php echo htmlspecialchars($mi['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="mt-2 text-sm text-slate-600">No menu items yet. The shop header will show only the logo and search.</p>
        <?php endif; ?>
    </section>
</main>
</body>
<?php if (!empty($media_items)): ?>
<script>
    (function(){
        var primarySelect = document.getElementById('primary_image_select');
        var primaryInput = document.getElementById('primary_image_url');
        var primaryBtn = document.getElementById('primary_image_use');
        if (primarySelect && primaryInput && primaryBtn) {
            primaryBtn.addEventListener('click', function() {
                if (primarySelect.value) {
                    primaryInput.value = primarySelect.value;
                }
            });
        }
        var gallerySelect = document.getElementById('gallery_image_select');
        var galleryInput = document.getElementById('gallery_images');
        var galleryBtn = document.getElementById('gallery_image_add');
        if (gallerySelect && galleryInput && galleryBtn) {
            galleryBtn.addEventListener('click', function() {
                if (!gallerySelect.value) return;
                var current = galleryInput.value.trim();
                if (current === '') {
                    galleryInput.value = gallerySelect.value;
                } else {
                    var parts = current.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                    if (parts.indexOf(gallerySelect.value) === -1) {
                        parts.push(gallerySelect.value);
                    }
                    galleryInput.value = parts.join(', ');
                }
            });
        }
    })();
</script>
<?php endif; ?>
<script>
    (function(){
        var primaryInput = document.getElementById('primary_image_url');
        var primaryPreview = document.getElementById('primary_image_preview');
        function updatePrimaryPreview() {
            if (!primaryInput || !primaryPreview) return;
            if (primaryInput.value.trim() !== '') {
                primaryPreview.src = primaryInput.value.trim();
                primaryPreview.classList.remove('hidden');
            } else {
                primaryPreview.classList.add('hidden');
            }
        }
        if (primaryInput) {
            primaryInput.addEventListener('input', updatePrimaryPreview);
        }
        updatePrimaryPreview();

        var galleryInput = document.getElementById('gallery_images');
        var galleryPreview = document.getElementById('gallery_preview');
        function renderGallery() {
            if (!galleryInput || !galleryPreview) return;
            var urls = galleryInput.value.split(',').map(function(s){return s.trim();}).filter(Boolean);
            galleryPreview.innerHTML = '';
            urls.forEach(function(url, idx){
                var wrap = document.createElement('div');
                wrap.className = 'flex flex-col items-center gap-1';
                var img = document.createElement('img');
                img.src = url;
                img.className = 'h-16 w-16 object-cover rounded border border-slate-200';
                var controls = document.createElement('div');
                controls.className = 'flex gap-1';
                var up = document.createElement('button');
                up.type = 'button';
                up.textContent = '↑';
                up.className = 'text-xs px-1 py-0.5 border rounded';
                up.onclick = function(){
                    if (idx === 0) return;
                    var tmp = urls[idx-1]; urls[idx-1] = urls[idx]; urls[idx] = tmp;
                    galleryInput.value = urls.join(', ');
                    renderGallery();
                };
                var down = document.createElement('button');
                down.type = 'button';
                down.textContent = '↓';
                down.className = 'text-xs px-1 py-0.5 border rounded';
                down.onclick = function(){
                    if (idx >= urls.length -1) return;
                    var tmp = urls[idx+1]; urls[idx+1] = urls[idx]; urls[idx] = tmp;
                    galleryInput.value = urls.join(', ');
                    renderGallery();
                };
                controls.appendChild(up);
                controls.appendChild(down);
                wrap.appendChild(img);
                wrap.appendChild(controls);
                galleryPreview.appendChild(wrap);
            });
        }
        if (galleryInput) {
            galleryInput.addEventListener('input', renderGallery);
            renderGallery();
        }
    })();
</script>
<script>
function editProduct(prod) {
    document.getElementById('product_id').value = prod.id || '';
    document.getElementById('product_name').value = prod.name || '';
    document.getElementById('product_sku').value = prod.sku || '';
    document.getElementById('product_slug').value = prod.slug || '';
    document.getElementById('product_price').value = prod.price_cents ? (parseInt(prod.price_cents) / 100).toFixed(2) : '0.00';
    document.getElementById('product_currency').value = prod.currency || 'USD';
    document.getElementById('product_stock').value = prod.stock || 0;
    document.getElementById('product_category_id').value = prod.category_id || '';
    document.getElementById('product_subcategory_id').value = prod.subcategory_id || '';
    document.getElementById('product_status').value = prod.status || 'active';
    document.getElementById('product_show_price').checked = prod.show_price != 0;
    document.getElementById('primary_image_url').value = prod.primary_image_url || '';
    document.getElementById('product_description').value = prod.description || '';

    // Gallery images
    var gallery = prod.gallery_images || '';
    try {
        var parsed = JSON.parse(gallery);
        if (Array.isArray(parsed)) gallery = parsed.join(', ');
    } catch(e) {}
    document.getElementById('gallery_images').value = gallery;

    // Trigger previews
    document.getElementById('primary_image_url').dispatchEvent(new Event('input'));
    document.getElementById('gallery_images').dispatchEvent(new Event('input'));

    // Update UI
    document.getElementById('save-product-btn').textContent = 'Update product';
    document.getElementById('cancel-edit-btn').classList.remove('hidden');

    // Scroll to form
    document.getElementById('product-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelProductEdit() {
    document.getElementById('product-form').reset();
    document.getElementById('product_id').value = '';
    document.getElementById('product_currency').value = 'USD';
    document.getElementById('product_show_price').checked = true;
    document.getElementById('save-product-btn').textContent = 'Save product';
    document.getElementById('cancel-edit-btn').classList.add('hidden');

    // Clear previews
    document.getElementById('primary_image_url').dispatchEvent(new Event('input'));
    document.getElementById('gallery_images').dispatchEvent(new Event('input'));
}

function editMenuItem(item) {
    document.getElementById('menu_item_id').value = item.id || '';
    document.getElementById('menu_item_label').value = item.label || '';
    document.getElementById('menu_item_url').value = item.url || '';
    document.getElementById('menu_item_sort').value = item.sort_order || 0;
    document.getElementById('menu_item_new_tab').checked = item.open_new_tab == 1;
    document.getElementById('save-menu-btn').textContent = 'Update menu item';
    document.getElementById('cancel-menu-edit-btn').classList.remove('hidden');
    document.getElementById('menu-item-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelMenuEdit() {
    document.getElementById('menu-item-form').reset();
    document.getElementById('menu_item_id').value = '';
    document.getElementById('menu_item_sort').value = '0';
    document.getElementById('save-menu-btn').textContent = 'Add menu item';
    document.getElementById('cancel-menu-edit-btn').classList.add('hidden');
}
</script>
<?php render_admin_sidebar_close(); ?>
</html>
