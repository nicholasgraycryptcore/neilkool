<?php
// Basic configuration for the website builder

// Directory where data will be stored
define('DATA_DIR', __DIR__ . '/data');
define('DB_FILE', DATA_DIR . '/site.sqlite');
// Legacy JSON storage file (used for one-time migration if present)
define('PAGES_JSON_FILE', DATA_DIR . '/pages.json');
// Directory for uploaded media files
define('MEDIA_DIR', DATA_DIR . '/media');

// Simple admin credentials (change these)
define('ADMIN_USERNAME', 'newageadmin');
define('ADMIN_PASSWORD', '@dminL0gin!!12');

// TinyMCE API key (get one free at tiny.cloud)
// Replace the placeholder value with your real key.
define('TINYMCE_API_KEY', 'sdirnyn69jpaaonjp7egqe79gu3qm71hp3c0pyghrdub3cw8');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}
if (!is_dir(MEDIA_DIR)) {
    mkdir(MEDIA_DIR, 0777, true);
}

// Get PDO connection to SQLite database
function get_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    init_db($db);

    return $db;
}

// Initialize database schema
function init_db(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS pages (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        content TEXT NOT NULL,
        updated_at TEXT,
        template TEXT NOT NULL DEFAULT "default",
        page_css TEXT,
        full_width INTEGER NOT NULL DEFAULT 0,
        show_title INTEGER NOT NULL DEFAULT 1,
        show_meta INTEGER NOT NULL DEFAULT 1
    )');

    // For existing installations, try to add new columns if they don't exist yet
    try {
        $db->exec('ALTER TABLE pages ADD COLUMN page_css TEXT');
    } catch (PDOException $e) {
        // Ignore if the column already exists
    }
    try {
        $db->exec('ALTER TABLE pages ADD COLUMN full_width INTEGER NOT NULL DEFAULT 0');
    } catch (PDOException $e) {
        // Ignore if the column already exists
    }
    try {
        $db->exec('ALTER TABLE pages ADD COLUMN show_title INTEGER NOT NULL DEFAULT 1');
    } catch (PDOException $e) {
        // Ignore if the column already exists
    }
    try {
        $db->exec('ALTER TABLE pages ADD COLUMN show_meta INTEGER NOT NULL DEFAULT 1');
    } catch (PDOException $e) {
        // Ignore if the column already exists
    }

    // Reusable page parts (snippets)
    $db->exec('CREATE TABLE IF NOT EXISTS parts (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        content TEXT NOT NULL,
        updated_at TEXT
    )');

    // Code snippets for scripts/styles/etc
    $db->exec('CREATE TABLE IF NOT EXISTS code_snippets (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        language TEXT NOT NULL,
        code TEXT NOT NULL,
        updated_at TEXT,
        auto_include INTEGER NOT NULL DEFAULT 0
    )');

    // For existing installations, try to add auto_include column if it doesn't exist yet
    try {
        $db->exec('ALTER TABLE code_snippets ADD COLUMN auto_include INTEGER NOT NULL DEFAULT 0');
    } catch (PDOException $e) {
        // Ignore if the column already exists
    }

    // Simple key/value settings storage
    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');

    // Media library records
    $db->exec('CREATE TABLE IF NOT EXISTS media (
        id TEXT PRIMARY KEY,
        filename TEXT NOT NULL,
        original_name TEXT NOT NULL,
        mime_type TEXT,
        size INTEGER,
        uploaded_at TEXT
    )');

    // Product catalog categories (supports nested categories via parent_id)
    $db->exec('CREATE TABLE IF NOT EXISTS categories (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        parent_id TEXT,
        created_at TEXT,
        updated_at TEXT
    )');

    // Products and basic inventory tracking
    $db->exec('CREATE TABLE IF NOT EXISTS products (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT,
        price_cents INTEGER NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT "USD",
        sku TEXT UNIQUE,
        category_id TEXT,
        subcategory_id TEXT,
        stock INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "active",
        image_id TEXT,
        primary_image_url TEXT,
        gallery_images TEXT,
        created_at TEXT,
        updated_at TEXT
    )');
    try {
        $db->exec('ALTER TABLE products ADD COLUMN primary_image_url TEXT');
    } catch (PDOException $e) {
        // ignore if exists
    }
    try {
        $db->exec('ALTER TABLE products ADD COLUMN gallery_images TEXT');
    } catch (PDOException $e) {
        // ignore if exists
    }
    try {
        $db->exec('ALTER TABLE products ADD COLUMN show_price INTEGER NOT NULL DEFAULT 1');
    } catch (PDOException $e) {
        // ignore if exists
    }

    // Inventory adjustments (manual or via orders)
    $db->exec('CREATE TABLE IF NOT EXISTS inventory_movements (
        id TEXT PRIMARY KEY,
        product_id TEXT NOT NULL,
        change_qty INTEGER NOT NULL,
        reason TEXT NOT NULL,
        reference_id TEXT,
        created_at TEXT,
        actor TEXT
    )');

    // Orders created via POS or storefront
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY,
        source TEXT NOT NULL DEFAULT "pos",
        status TEXT NOT NULL DEFAULT "pending",
        subtotal_cents INTEGER NOT NULL DEFAULT 0,
        tax_cents INTEGER NOT NULL DEFAULT 0,
        discount_cents INTEGER NOT NULL DEFAULT 0,
        total_cents INTEGER NOT NULL DEFAULT 0,
        customer_name TEXT,
        customer_contact TEXT,
        created_at TEXT,
        completed_at TEXT
    )');

    // Items included in an order
    $db->exec('CREATE TABLE IF NOT EXISTS order_items (
        id TEXT PRIMARY KEY,
        order_id TEXT NOT NULL,
        product_id TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price_cents INTEGER NOT NULL,
        total_cents INTEGER NOT NULL
    )');

    // Payments recorded against orders
    $db->exec('CREATE TABLE IF NOT EXISTS payments (
        id TEXT PRIMARY KEY,
        order_id TEXT NOT NULL,
        method TEXT NOT NULL,
        amount_cents INTEGER NOT NULL,
        received_at TEXT,
        received_by TEXT,
        note TEXT
    )');

    // Suppliers for purchasing
    $db->exec('CREATE TABLE IF NOT EXISTS suppliers (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        contact_name TEXT,
        contact_email TEXT,
        contact_phone TEXT,
        notes TEXT,
        created_at TEXT,
        updated_at TEXT
    )');

    // Purchase orders
    $db->exec('CREATE TABLE IF NOT EXISTS purchase_orders (
        id TEXT PRIMARY KEY,
        supplier_id TEXT,
        status TEXT NOT NULL DEFAULT "draft",
        subtotal_cents INTEGER NOT NULL DEFAULT 0,
        tax_cents INTEGER NOT NULL DEFAULT 0,
        total_cents INTEGER NOT NULL DEFAULT 0,
        notes TEXT,
        ordered_at TEXT,
        received_at TEXT,
        created_at TEXT,
        updated_at TEXT
    )');

    // Purchase order items
    $db->exec('CREATE TABLE IF NOT EXISTS purchase_order_items (
        id TEXT PRIMARY KEY,
        purchase_order_id TEXT NOT NULL,
        product_id TEXT,
        description TEXT,
        quantity INTEGER NOT NULL,
        unit_cost_cents INTEGER NOT NULL,
        total_cents INTEGER NOT NULL
    )');

    // Expenses
    $db->exec('CREATE TABLE IF NOT EXISTS expenses (
        id TEXT PRIMARY KEY,
        category TEXT,
        supplier_id TEXT,
        amount_cents INTEGER NOT NULL DEFAULT 0,
        tax_cents INTEGER NOT NULL DEFAULT 0,
        total_cents INTEGER NOT NULL DEFAULT 0,
        description TEXT,
        spent_at TEXT,
        attachment_url TEXT,
        created_at TEXT,
        updated_at TEXT
    )');

    // Users with roles/access levels
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "admin",
        must_change_password INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT
    )');
    // Backfill new columns if missing
    try {
        $db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');
    } catch (PDOException $e) {
        error_log('Migration note (users.must_change_password): ' . $e->getMessage());
    }
    try {
        $db->exec('UPDATE users SET must_change_password = 0 WHERE must_change_password IS NULL');
    } catch (PDOException $e) {
        error_log('Migration note (users.must_change_password default set): ' . $e->getMessage());
    }

    // Simple audit log for critical actions
    $db->exec('CREATE TABLE IF NOT EXISTS audit_logs (
        id TEXT PRIMARY KEY,
        actor TEXT,
        action TEXT NOT NULL,
        entity_type TEXT,
        entity_id TEXT,
        meta TEXT,
        created_at TEXT,
        ip_address TEXT
    )');

    // Shop navigation menu items
    $db->exec('CREATE TABLE IF NOT EXISTS shop_menu_items (
        id TEXT PRIMARY KEY,
        label TEXT NOT NULL,
        url TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        open_new_tab INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT
    )');
}

// Load all pages from SQLite storage
function load_pages(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, title, slug, content, updated_at, template, page_css, full_width, show_title, show_meta FROM pages ORDER BY title COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    if (is_array($rows) && count($rows) > 0) {
        return $rows;
    }

    // Optional: migrate from legacy JSON storage if it exists and DB is empty
    if (file_exists(PAGES_JSON_FILE)) {
        $json = file_get_contents(PAGES_JSON_FILE);
        $data = json_decode($json, true);
        if (is_array($data) && !empty($data)) {
            foreach ($data as &$page) {
                if (!isset($page['template'])) {
                    $page['template'] = 'default';
                }
            }
            unset($page);
            save_pages($data);
            return $data;
        }
    }

    return [];
}

// Save all pages to SQLite storage (replaces current set)
function save_pages(array $pages): void
{
    $db = get_db();
    $db->beginTransaction();
    $db->exec('DELETE FROM pages');
    $stmt = $db->prepare('INSERT INTO pages (id, title, slug, content, updated_at, template, page_css, full_width, show_title, show_meta)
                          VALUES (:id, :title, :slug, :content, :updated_at, :template, :page_css, :full_width, :show_title, :show_meta)');

    foreach ($pages as $page) {
        $stmt->execute([
            ':id' => $page['id'],
            ':title' => $page['title'],
            ':slug' => $page['slug'],
            ':content' => $page['content'],
            ':updated_at' => $page['updated_at'] ?? null,
            ':template' => $page['template'] ?? 'default',
            ':page_css' => $page['page_css'] ?? null,
            ':full_width' => !empty($page['full_width']) ? 1 : 0,
            ':show_title' => array_key_exists('show_title', $page) ? (!empty($page['show_title']) ? 1 : 0) : 1,
            ':show_meta' => array_key_exists('show_meta', $page) ? (!empty($page['show_meta']) ? 1 : 0) : 1,
        ]);
    }

    $db->commit();
}

// Find page by ID within an array of pages
function find_page_by_id(array $pages, string $id): ?array
{
    foreach ($pages as $page) {
        if ($page['id'] === $id) {
            return $page;
        }
    }
    return null;
}

// Find page by slug within an array of pages
function find_page_by_slug(array $pages, string $slug): ?array
{
    foreach ($pages as $page) {
        if ($page['slug'] === $slug) {
            return $page;
        }
    }
    return null;
}

// Generate a simple slug from title
function slugify(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'page-' . uniqid();
    }
    return $slug;
}

// Generate a unique ID
function generate_id(): string
{
    return uniqid('page_', true);
}

// Load all reusable parts sorted by name
function load_parts(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, name, content, updated_at FROM parts ORDER BY name COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Save (insert or update) a reusable part
function save_part(string $id, string $name, string $content): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO parts (id, name, content, updated_at)
                          VALUES (:id, :name, :content, :updated_at)
                          ON CONFLICT(id) DO UPDATE SET
                            name = excluded.name,
                            content = excluded.content,
                            updated_at = excluded.updated_at');
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':content' => $content,
        ':updated_at' => date('c'),
    ]);
}

// Delete a reusable part by ID
function delete_part(string $id): void
{
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM parts WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

// Find a single part by ID
function find_part_by_id(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, content, updated_at FROM parts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Load all media records sorted by newest first
function load_media(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, filename, original_name, mime_type, size, uploaded_at FROM media ORDER BY uploaded_at DESC');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Save a media record
function save_media(string $id, string $filename, string $originalName, ?string $mimeType, int $size): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO media (id, filename, original_name, mime_type, size, uploaded_at)
                          VALUES (:id, :filename, :original_name, :mime_type, :size, :uploaded_at)');
    $stmt->execute([
        ':id' => $id,
        ':filename' => $filename,
        ':original_name' => $originalName,
        ':mime_type' => $mimeType,
        ':size' => $size,
        ':uploaded_at' => date('c'),
    ]);
}

// Find a media item by ID
function find_media_by_id(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, filename, original_name, mime_type, size, uploaded_at FROM media WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Delete a media record (and optionally its file)
function delete_media(string $id): void
{
    $item = find_media_by_id($id);
    if ($item && !empty($item['filename'])) {
        $path = MEDIA_DIR . '/' . $item['filename'];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $db = get_db();
    $stmt = $db->prepare('DELETE FROM media WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

// Load all code snippets sorted by name
function load_snippets(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, name, language, code, updated_at, auto_include FROM code_snippets ORDER BY name COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Save (insert or update) a code snippet
function save_snippet(string $id, string $name, string $language, string $code, bool $autoInclude = false): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO code_snippets (id, name, language, code, updated_at, auto_include)
                          VALUES (:id, :name, :language, :code, :updated_at, :auto_include)
                          ON CONFLICT(id) DO UPDATE SET
                            name = excluded.name,
                            language = excluded.language,
                            code = excluded.code,
                            updated_at = excluded.updated_at,
                            auto_include = excluded.auto_include');
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':language' => $language,
        ':code' => $code,
        ':updated_at' => date('c'),
        ':auto_include' => $autoInclude ? 1 : 0,
    ]);
}

// Delete a code snippet by ID
function delete_snippet(string $id): void
{
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM code_snippets WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

// Find a single code snippet by ID
function find_snippet_by_id(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, language, code, updated_at, auto_include FROM code_snippets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Load only snippets that should be auto-included on every page
function load_auto_include_snippets(): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, language, code, updated_at, auto_include FROM code_snippets WHERE auto_include = 1 ORDER BY name COLLATE NOCASE');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Get a simple string setting from the settings table
function get_setting(string $name, ?string $default = null): ?string
{
    $db = get_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && array_key_exists('value', $row)) {
        return $row['value'];
    }

    return $default;
}

// Store or clear a simple string setting
function set_setting(string $name, ?string $value): void
{
    $db = get_db();

    if ($value === null) {
        $stmt = $db->prepare('DELETE FROM settings WHERE name = :name');
        $stmt->execute([':name' => $name]);
        return;
    }

    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (name, value) VALUES (:name, :value)');
    $stmt->execute([':name' => $name, ':value' => $value]);
}

// --- User management helpers ---

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function user_must_change_password(array $user): bool
{
    return !empty($user['must_change_password']);
}

function create_user(string $username, string $password, string $role = 'admin'): string
{
    $db = get_db();
    $id = generate_entity_id('user_');
    $stmt = $db->prepare('INSERT INTO users (id, username, password_hash, role, must_change_password, created_at, updated_at)
                          VALUES (:id, :username, :password_hash, :role, :must_change_password, :created_at, :updated_at)');
    $now = date('c');
    $stmt->execute([
        ':id' => $id,
        ':username' => $username,
        ':password_hash' => hash_password($password),
        ':role' => $role,
        ':must_change_password' => 0,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    return $id;
}

function update_user_role(string $id, string $role): void
{
    $db = get_db();
    $stmt = $db->prepare('UPDATE users SET role = :role, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([':role' => $role, ':updated_at' => date('c'), ':id' => $id]);
}

function update_user_password(string $id, string $password): void
{
    $db = get_db();
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([':hash' => hash_password($password), ':updated_at' => date('c'), ':id' => $id]);
}

function find_user_by_username(string $username): ?array
{
    $db = get_db();
    try {
        $stmt = $db->prepare('SELECT id, username, password_hash, role, must_change_password FROM users WHERE username = :username LIMIT 1');
    } catch (PDOException $e) {
        // Fallback if column missing; callers handle absence as false/0
        $stmt = $db->prepare('SELECT id, username, password_hash, role, 0 as must_change_password FROM users WHERE username = :username LIMIT 1');
    }
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function load_users(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, username, role, must_change_password, created_at, updated_at FROM users ORDER BY username COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function ensure_default_admin(): void
{
    $db = get_db();
    $stmt = $db->query('SELECT COUNT(*) AS cnt FROM users');
    $countRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $countRow ? (int)$countRow['cnt'] : 0;
    if ($count === 0) {
        // Force password change on first login for seeded admin
        $id = create_user(ADMIN_USERNAME, ADMIN_PASSWORD, 'admin');
        $flag = $db->prepare('UPDATE users SET must_change_password = 1 WHERE id = :id');
        $flag->execute([':id' => $id]);
    }
}

// --- Supplier helpers ---
function save_supplier(array $data): string
{
    $db = get_db();
    $id = $data['id'] ?? generate_entity_id('sup_');
    $stmt = $db->prepare('INSERT INTO suppliers (id, name, contact_name, contact_email, contact_phone, notes, created_at, updated_at)
                          VALUES (:id, :name, :contact_name, :contact_email, :contact_phone, :notes, :created_at, :updated_at)
                          ON CONFLICT(id) DO UPDATE SET
                            name = excluded.name,
                            contact_name = excluded.contact_name,
                            contact_email = excluded.contact_email,
                            contact_phone = excluded.contact_phone,
                            notes = excluded.notes,
                            updated_at = excluded.updated_at');
    $now = date('c');
    $stmt->execute([
        ':id' => $id,
        ':name' => $data['name'],
        ':contact_name' => $data['contact_name'] ?? null,
        ':contact_email' => $data['contact_email'] ?? null,
        ':contact_phone' => $data['contact_phone'] ?? null,
        ':notes' => $data['notes'] ?? null,
        ':created_at' => $data['created_at'] ?? $now,
        ':updated_at' => $now,
    ]);
    return $id;
}

function load_suppliers(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, name, contact_name, contact_email, contact_phone, notes, created_at, updated_at FROM suppliers ORDER BY name COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function find_supplier_by_id(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, contact_name, contact_email, contact_phone, notes, created_at, updated_at FROM suppliers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// --- Purchase orders ---
function create_purchase_order(array $data, array $items): string
{
    $db = get_db();
    $db->beginTransaction();
    try {
        $id = $data['id'] ?? generate_entity_id('po_');
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int)$item['unit_cost_cents']) * ((int)$item['quantity']);
        }
        $tax = (int)($data['tax_cents'] ?? 0);
        $total = $subtotal + $tax;
        $now = date('c');
        $stmt = $db->prepare('INSERT INTO purchase_orders (id, supplier_id, status, subtotal_cents, tax_cents, total_cents, notes, ordered_at, created_at, updated_at)
                              VALUES (:id, :supplier_id, :status, :subtotal_cents, :tax_cents, :total_cents, :notes, :ordered_at, :created_at, :updated_at)');
        $stmt->execute([
            ':id' => $id,
            ':supplier_id' => $data['supplier_id'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':subtotal_cents' => $subtotal,
            ':tax_cents' => $tax,
            ':total_cents' => $total,
            ':notes' => $data['notes'] ?? null,
            ':ordered_at' => $data['ordered_at'] ?? $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $itemStmt = $db->prepare('INSERT INTO purchase_order_items (id, purchase_order_id, product_id, description, quantity, unit_cost_cents, total_cents)
                                  VALUES (:id, :purchase_order_id, :product_id, :description, :quantity, :unit_cost_cents, :total_cents)');
        foreach ($items as $item) {
            $lineTotal = ((int)$item['unit_cost_cents']) * ((int)$item['quantity']);
            $itemStmt->execute([
                ':id' => generate_entity_id('poi_'),
                ':purchase_order_id' => $id,
                ':product_id' => $item['product_id'] ?? null,
                ':description' => $item['description'] ?? '',
                ':quantity' => (int)$item['quantity'],
                ':unit_cost_cents' => (int)$item['unit_cost_cents'],
                ':total_cents' => $lineTotal,
            ]);
        }

        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function load_purchase_orders(array $filters = []): array
{
    $db = get_db();
    $where = [];
    $params = [];
    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['supplier_id'])) {
        $where[] = 'supplier_id = :supplier_id';
        $params[':supplier_id'] = $filters['supplier_id'];
    }
    $query = 'SELECT * FROM purchase_orders';
    if ($where) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    $query .= ' ORDER BY created_at DESC';
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    return is_array($orders) ? $orders : [];
}

function load_purchase_order_items(string $purchaseOrderId): array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, purchase_order_id, product_id, description, quantity, unit_cost_cents, total_cents FROM purchase_order_items WHERE purchase_order_id = :id');
    $stmt->execute([':id' => $purchaseOrderId]);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function receive_purchase_order(string $purchaseOrderId, ?string $actor = null): void
{
    $db = get_db();
    $db->beginTransaction();
    try {
        $now = date('c');
        $items = load_purchase_order_items($purchaseOrderId);
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                adjust_inventory($item['product_id'], (int)$item['quantity'], 'po_receive', $actor, $purchaseOrderId);
            }
        }
        $stmt = $db->prepare('UPDATE purchase_orders SET status = "received", received_at = :received_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([':received_at' => $now, ':updated_at' => $now, ':id' => $purchaseOrderId]);
        log_action('receive_po', $actor, 'purchase_order', $purchaseOrderId, ['items' => count($items)]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function update_purchase_order(string $id, array $data, array $items): void
{
    $db = get_db();
    $db->beginTransaction();
    try {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int)$item['unit_cost_cents']) * ((int)$item['quantity']);
        }
        $tax = (int)($data['tax_cents'] ?? 0);
        $total = $subtotal + $tax;
        $now = date('c');
        $stmt = $db->prepare('UPDATE purchase_orders SET supplier_id = :supplier_id, status = :status, subtotal_cents = :subtotal_cents, tax_cents = :tax_cents, total_cents = :total_cents, notes = :notes, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':supplier_id' => $data['supplier_id'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':subtotal_cents' => $subtotal,
            ':tax_cents' => $tax,
            ':total_cents' => $total,
            ':notes' => $data['notes'] ?? null,
            ':updated_at' => $now,
            ':id' => $id,
        ]);

        $db->prepare('DELETE FROM purchase_order_items WHERE purchase_order_id = :id')->execute([':id' => $id]);

        $itemStmt = $db->prepare('INSERT INTO purchase_order_items (id, purchase_order_id, product_id, description, quantity, unit_cost_cents, total_cents)
                                  VALUES (:id, :purchase_order_id, :product_id, :description, :quantity, :unit_cost_cents, :total_cents)');
        foreach ($items as $item) {
            $lineTotal = ((int)$item['unit_cost_cents']) * ((int)$item['quantity']);
            $itemStmt->execute([
                ':id' => generate_entity_id('poi_'),
                ':purchase_order_id' => $id,
                ':product_id' => $item['product_id'] ?? null,
                ':description' => $item['description'] ?? '',
                ':quantity' => (int)$item['quantity'],
                ':unit_cost_cents' => (int)$item['unit_cost_cents'],
                ':total_cents' => $lineTotal,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function delete_purchase_order(string $id): void
{
    $db = get_db();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM purchase_order_items WHERE purchase_order_id = :id')->execute([':id' => $id]);
        $db->prepare('DELETE FROM purchase_orders WHERE id = :id')->execute([':id' => $id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function find_purchase_order(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM purchase_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// --- Expenses ---
function save_expense(array $data): string
{
    $db = get_db();
    $id = $data['id'] ?? generate_entity_id('exp_');
    $tax = (int)($data['tax_cents'] ?? 0);
    $amount = (int)($data['amount_cents'] ?? 0);
    $total = $amount + $tax;
    $now = date('c');
    $stmt = $db->prepare('INSERT INTO expenses (id, category, supplier_id, amount_cents, tax_cents, total_cents, description, spent_at, attachment_url, created_at, updated_at)
                          VALUES (:id, :category, :supplier_id, :amount_cents, :tax_cents, :total_cents, :description, :spent_at, :attachment_url, :created_at, :updated_at)
                          ON CONFLICT(id) DO UPDATE SET
                            category = excluded.category,
                            supplier_id = excluded.supplier_id,
                            amount_cents = excluded.amount_cents,
                            tax_cents = excluded.tax_cents,
                            total_cents = excluded.total_cents,
                            description = excluded.description,
                            spent_at = excluded.spent_at,
                            attachment_url = excluded.attachment_url,
                            updated_at = excluded.updated_at');
    $stmt->execute([
        ':id' => $id,
        ':category' => $data['category'] ?? null,
        ':supplier_id' => $data['supplier_id'] ?? null,
        ':amount_cents' => $amount,
        ':tax_cents' => $tax,
        ':total_cents' => $total,
        ':description' => $data['description'] ?? null,
        ':spent_at' => $data['spent_at'] ?? date('c'),
        ':attachment_url' => $data['attachment_url'] ?? null,
        ':created_at' => $data['created_at'] ?? $now,
        ':updated_at' => $now,
    ]);
    return $id;
}

function load_expenses(array $filters = []): array
{
    $db = get_db();
    $where = [];
    $params = [];
    if (!empty($filters['supplier_id'])) {
        $where[] = 'supplier_id = :supplier_id';
        $params[':supplier_id'] = $filters['supplier_id'];
    }
    if (!empty($filters['category'])) {
        $where[] = 'category = :category';
        $params[':category'] = $filters['category'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'spent_at >= :from';
        $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'spent_at <= :to';
        $params[':to'] = $filters['to'];
    }
    $query = 'SELECT * FROM expenses';
    if ($where) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    $query .= ' ORDER BY spent_at DESC';
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function delete_expense(string $id): void
{
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM expenses WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

// Generate a unique ID with a custom prefix (used for ecommerce entities)
function generate_entity_id(string $prefix): string
{
    return uniqid($prefix, true);
}

// --- Ecommerce helpers ---

// Create or update a category
function save_category(array $data): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO categories (id, name, slug, parent_id, created_at, updated_at)
                          VALUES (:id, :name, :slug, :parent_id, :created_at, :updated_at)
                          ON CONFLICT(id) DO UPDATE SET
                            name = excluded.name,
                            slug = excluded.slug,
                            parent_id = excluded.parent_id,
                            updated_at = excluded.updated_at');
    $now = date('c');
    $stmt->execute([
        ':id' => $data['id'] ?? generate_entity_id('cat_'),
        ':name' => $data['name'],
        ':slug' => $data['slug'] ?? slugify($data['name']),
        ':parent_id' => $data['parent_id'] ?? null,
        ':created_at' => $data['created_at'] ?? $now,
        ':updated_at' => $now,
    ]);
}

// Fetch all categories (optionally only top-level)
function load_categories(?bool $onlyTopLevel = null): array
{
    $db = get_db();
    $query = 'SELECT id, name, slug, parent_id, created_at, updated_at FROM categories';
    if ($onlyTopLevel === true) {
        $query .= ' WHERE parent_id IS NULL';
    }
    $query .= ' ORDER BY name COLLATE NOCASE';
    $stmt = $db->query($query);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Create or update a product; returns product ID
function save_product(array $data): string
{
    $db = get_db();
    $productId = $data['id'] ?? generate_entity_id('prod_');

    $gallery = $data['gallery_images'] ?? null;
    if (is_string($gallery)) {
        $gallery = array_filter(array_map('trim', explode(',', $gallery)), 'strlen');
    }
    $galleryJson = $gallery ? json_encode(array_values($gallery)) : null;

    $stmt = $db->prepare('INSERT INTO products (
            id, name, slug, description, price_cents, currency, sku,
            category_id, subcategory_id, stock, status, image_id, primary_image_url, gallery_images, show_price, created_at, updated_at
        ) VALUES (
            :id, :name, :slug, :description, :price_cents, :currency, :sku,
            :category_id, :subcategory_id, :stock, :status, :image_id, :primary_image_url, :gallery_images, :show_price, :created_at, :updated_at
        ) ON CONFLICT(id) DO UPDATE SET
            name = excluded.name,
            slug = excluded.slug,
            description = excluded.description,
            price_cents = excluded.price_cents,
            currency = excluded.currency,
            sku = excluded.sku,
            category_id = excluded.category_id,
            subcategory_id = excluded.subcategory_id,
            stock = excluded.stock,
            status = excluded.status,
            image_id = excluded.image_id,
            primary_image_url = excluded.primary_image_url,
            gallery_images = excluded.gallery_images,
            show_price = excluded.show_price,
            updated_at = excluded.updated_at');

    $now = date('c');
    $stmt->execute([
        ':id' => $productId,
        ':name' => $data['name'],
        ':slug' => $data['slug'] ?? slugify($data['name']),
        ':description' => $data['description'] ?? null,
        ':price_cents' => (int)($data['price_cents'] ?? 0),
        ':currency' => $data['currency'] ?? 'USD',
        ':sku' => $data['sku'] ?? null,
        ':category_id' => $data['category_id'] ?? null,
        ':subcategory_id' => $data['subcategory_id'] ?? null,
        ':stock' => (int)($data['stock'] ?? 0),
        ':status' => $data['status'] ?? 'active',
        ':image_id' => $data['image_id'] ?? null,
        ':primary_image_url' => $data['primary_image_url'] ?? null,
        ':gallery_images' => $galleryJson,
        ':show_price' => (int)($data['show_price'] ?? 1),
        ':created_at' => $data['created_at'] ?? $now,
        ':updated_at' => $now,
    ]);

    return $productId;
}

// Load products with optional filters
function load_products(array $filters = []): array
{
    $db = get_db();
    $where = [];
    $params = [];

    if (isset($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = $filters['status'];
    }
    if (isset($filters['category_id'])) {
        $where[] = '(category_id = :category_id OR subcategory_id = :category_id)';
        $params[':category_id'] = $filters['category_id'];
    }

    $query = 'SELECT id, name, slug, description, price_cents, currency, sku, category_id, subcategory_id, stock, status, image_id, primary_image_url, gallery_images, show_price, created_at, updated_at FROM products';
    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    $query .= ' ORDER BY name COLLATE NOCASE';

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

// Find a single product by slug
function find_product_by_slug(string $slug): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, slug, description, price_cents, currency, sku, category_id, subcategory_id, stock, status, image_id, primary_image_url, gallery_images, show_price, created_at, updated_at FROM products WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Find a single product by ID
function find_product_by_id_global(string $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, slug, description, price_cents, currency, sku, category_id, subcategory_id, stock, status, image_id, primary_image_url, gallery_images, show_price, created_at, updated_at FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Decode gallery images JSON to array of URLs
function product_gallery_urls(array $product): array
{
    if (empty($product['gallery_images'])) {
        return [];
    }
    $decoded = json_decode((string)$product['gallery_images'], true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
    }
    return [];
}

function product_primary_image_url(array $product): ?string
{
    if (!empty($product['primary_image_url'])) {
        return $product['primary_image_url'];
    }
    return null;
}

// Adjust inventory and record the movement; returns new stock level
function adjust_inventory(string $productId, int $changeQty, string $reason, ?string $actor = null, ?string $referenceId = null): int
{
    $db = get_db();
    $managedTransaction = !$db->inTransaction();
    if ($managedTransaction) {
        $db->beginTransaction();
    }
    $now = date('c');

    $stmt = $db->prepare('SELECT stock FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentStock = $row ? (int)$row['stock'] : 0;

    $newStock = $currentStock + $changeQty;
    if ($newStock < 0) {
        if ($managedTransaction) {
            $db->rollBack();
        }
        throw new RuntimeException('Insufficient stock for adjustment.');
    }

    $update = $db->prepare('UPDATE products SET stock = :stock, updated_at = :updated_at WHERE id = :id');
    $update->execute([
        ':stock' => $newStock,
        ':updated_at' => $now,
        ':id' => $productId,
    ]);

    $move = $db->prepare('INSERT INTO inventory_movements (id, product_id, change_qty, reason, reference_id, created_at, actor)
                          VALUES (:id, :product_id, :change_qty, :reason, :reference_id, :created_at, :actor)');
    $move->execute([
        ':id' => generate_entity_id('move_'),
        ':product_id' => $productId,
        ':change_qty' => $changeQty,
        ':reason' => $reason,
        ':reference_id' => $referenceId,
        ':created_at' => $now,
        ':actor' => $actor,
    ]);

    if ($managedTransaction) {
        $db->commit();
    }
    return $newStock;
}

// Create an order with items and optional stock reservation/subtraction
function create_order(array $orderData, array $items, bool $subtractStock = true): string
{
    $db = get_db();
    $db->beginTransaction();

    try {
        $now = date('c');

        $orderId = $orderData['id'] ?? generate_entity_id('ord_');
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int)$item['unit_price_cents']) * ((int)$item['quantity']);
        }

        $tax = (int)($orderData['tax_cents'] ?? 0);
        $discount = (int)($orderData['discount_cents'] ?? 0);
        $total = $subtotal + $tax - $discount;

        $stmt = $db->prepare('INSERT INTO orders (
            id, source, status, subtotal_cents, tax_cents, discount_cents, total_cents, customer_name, customer_contact, created_at
        ) VALUES (
            :id, :source, :status, :subtotal_cents, :tax_cents, :discount_cents, :total_cents, :customer_name, :customer_contact, :created_at
        )');
        $stmt->execute([
            ':id' => $orderId,
            ':source' => $orderData['source'] ?? 'pos',
            ':status' => $orderData['status'] ?? 'pending',
            ':subtotal_cents' => $subtotal,
            ':tax_cents' => $tax,
            ':discount_cents' => $discount,
            ':total_cents' => $total,
            ':customer_name' => $orderData['customer_name'] ?? null,
            ':customer_contact' => $orderData['customer_contact'] ?? null,
            ':created_at' => $now,
        ]);

        $itemStmt = $db->prepare('INSERT INTO order_items (id, order_id, product_id, quantity, unit_price_cents, total_cents)
                                  VALUES (:id, :order_id, :product_id, :quantity, :unit_price_cents, :total_cents)');

        foreach ($items as $item) {
            $lineTotal = ((int)$item['unit_price_cents']) * ((int)$item['quantity']);
            $itemStmt->execute([
                ':id' => generate_entity_id('item_'),
                ':order_id' => $orderId,
                ':product_id' => $item['product_id'],
                ':quantity' => (int)$item['quantity'],
                ':unit_price_cents' => (int)$item['unit_price_cents'],
                ':total_cents' => $lineTotal,
            ]);

            if ($subtractStock) {
                adjust_inventory($item['product_id'], -1 * (int)$item['quantity'], 'order', $orderData['actor'] ?? null, $orderId);
            }
        }

        $db->commit();
        return $orderId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// Record a payment and mark the order paid if fully covered
function record_payment(string $orderId, int $amountCents, string $method, ?string $receivedBy = null, ?string $note = null): void
{
    $db = get_db();
    $db->beginTransaction();
    $now = date('c');

    try {
        $paymentId = generate_entity_id('pay_');
        $stmt = $db->prepare('INSERT INTO payments (id, order_id, method, amount_cents, received_at, received_by, note)
                              VALUES (:id, :order_id, :method, :amount_cents, :received_at, :received_by, :note)');
        $stmt->execute([
            ':id' => $paymentId,
            ':order_id' => $orderId,
            ':method' => $method,
            ':amount_cents' => $amountCents,
            ':received_at' => $now,
            ':received_by' => $receivedBy,
            ':note' => $note,
        ]);

        // Check total payments against order total
        $totalStmt = $db->prepare('SELECT total_cents FROM orders WHERE id = :id LIMIT 1');
        $totalStmt->execute([':id' => $orderId]);
        $order = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $orderTotal = $order ? (int)$order['total_cents'] : 0;

        $paidStmt = $db->prepare('SELECT SUM(amount_cents) AS paid FROM payments WHERE order_id = :order_id');
        $paidStmt->execute([':order_id' => $orderId]);
        $paidRow = $paidStmt->fetch(PDO::FETCH_ASSOC);
        $paidAmount = $paidRow && isset($paidRow['paid']) ? (int)$paidRow['paid'] : 0;

        if ($paidAmount >= $orderTotal && $orderTotal > 0) {
            $update = $db->prepare('UPDATE orders SET status = "paid", completed_at = :completed_at WHERE id = :id');
            $update->execute([':completed_at' => $now, ':id' => $orderId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// Write a generic audit log entry
function log_action(string $action, ?string $actor = null, ?string $entityType = null, ?string $entityId = null, array $meta = [], ?string $ip = null): void
{
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO audit_logs (id, actor, action, entity_type, entity_id, meta, created_at, ip_address)
                          VALUES (:id, :actor, :action, :entity_type, :entity_id, :meta, :created_at, :ip_address)');
    $stmt->execute([
        ':id' => generate_entity_id('log_'),
        ':actor' => $actor,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':meta' => json_encode($meta),
        ':created_at' => date('c'),
        ':ip_address' => $ip,
    ]);
}

// Ensure a product detail page exists (auto-generated if missing)
function ensure_product_page(string $productId): void
{
    $product = find_product_by_id_global($productId);
    if (!$product) {
        return;
    }

    $slug = 'product-' . $product['slug'];
    $db = get_db();

    $stmt = $db->prepare('SELECT id FROM pages WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return;
    }

    $pageId = generate_id();
    $title = 'Product: ' . $product['name'];
    $gallery = product_gallery_urls($product);
    $galleryHtml = '';
    if (!empty($gallery)) {
        $galleryHtml .= '<div class="product-gallery" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;">';
        foreach ($gallery as $img) {
            $safe = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
            $galleryHtml .= '<img src="' . $safe . '" alt="' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '" style="max-width:220px;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:#fff;">';
        }
        $galleryHtml .= '</div>';
    }

    $primary = !empty($product['primary_image_url']) ? htmlspecialchars($product['primary_image_url'], ENT_QUOTES, 'UTF-8') : '';
    $primaryHtml = $primary !== '' ? '<img src="' . $primary . '" alt="' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '" style="max-width:320px;border-radius:10px;border:1px solid #e5e7eb;margin-bottom:16px;">' : '';

    $content = '<section style="padding:24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">'
        . '<h2 style="margin-top:0;font-size:26px;color:#0f172a;">' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '</h2>'
        . $primaryHtml
        . '<p style="color:#475569;font-size:15px;line-height:1.6;">' . htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div style="margin:18px 0;">[product slug="' . htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8') . '" show="name,price,category"]</div>'
        . $galleryHtml
        . '</section>';

    $insert = $db->prepare('INSERT INTO pages (id, title, slug, content, updated_at, template, page_css, full_width, show_title, show_meta)
                            VALUES (:id, :title, :slug, :content, :updated_at, :template, :page_css, :full_width, :show_title, :show_meta)');
    $insert->execute([
        ':id' => $pageId,
        ':title' => $title,
        ':slug' => $slug,
        ':content' => $content,
        ':updated_at' => date('c'),
        ':template' => 'default',
        ':page_css' => null,
        ':full_width' => 0,
        ':show_title' => 1,
        ':show_meta' => 1,
    ]);
}

// Render a product card suitable for embedding on content pages
function render_product_embed(array $product, ?string $returnUrl = null, array $options = [], array $categoryLookup = []): string
{
    $name = htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $price = (int)($product['price_cents'] ?? 0);
    $currency = htmlspecialchars($product['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8');
    $stock = (int)($product['stock'] ?? 0);
    $productId = htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8');
    $priceText = $currency . ' ' . number_format($price / 100, 2);
    $categoryLabel = '';
    if (!empty($product['category_id']) && isset($categoryLookup[$product['category_id']])) {
        $categoryLabel = $categoryLookup[$product['category_id']];
    }
    if (!empty($product['subcategory_id']) && isset($categoryLookup[$product['subcategory_id']])) {
        $categoryLabel = $categoryLabel !== '' ? $categoryLabel . ' / ' . $categoryLookup[$product['subcategory_id']] : $categoryLookup[$product['subcategory_id']];
    }
    $showFields = $options['show'] ?? ['name', 'description', 'price', 'category'];
    $showName = in_array('name', $showFields, true);
    $showDescription = in_array('description', $showFields, true);
    $showPrice = in_array('price', $showFields, true);
    $showCategory = in_array('category', $showFields, true);
    $primary = product_primary_image_url($product);
    $imageHtml = '';
    if ($primary) {
        $imageHtml = '<div style="margin-bottom:10px;"><img src="' . htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" style="max-width:100%;border-radius:8px;border:1px solid #e2e8f0;"></div>';
    }
    $returnField = '';
    if ($returnUrl && strpos($returnUrl, '://') === false) {
        $returnField = '<input type="hidden" name="return" value="' . htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') . '">';
    }

    if ($stock <= 0 || ($product['status'] ?? 'inactive') !== 'active') {
        return '<div class="embedded-product" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:12px 0;background:#f8fafc;">'
            . $imageHtml
            . ($showName ? '<div style="font-weight:600;font-size:16px;margin-bottom:4px;">' . $name . '</div>' : '')
            . ($showDescription ? '<div style="color:#475569;font-size:14px;margin-bottom:8px;">' . $desc . '</div>' : '')
            . ($showCategory && $categoryLabel !== '' ? '<div style="color:#64748b;font-size:13px;margin-bottom:6px;">' . htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') . '</div>' : '')
            . '<div style="color:#ef4444;font-weight:600;">Out of stock</div>'
            . '</div>';
    }

    return '<div class="embedded-product" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:12px 0;background:#f8fafc;">'
        . $imageHtml
        . ($showName ? '<div style="font-weight:600;font-size:16px;margin-bottom:4px;">' . $name . '</div>' : '')
        . ($showDescription ? '<div style="color:#475569;font-size:14px;margin-bottom:8px;">' . $desc . '</div>' : '')
        . ($showCategory && $categoryLabel !== '' ? '<div style="color:#64748b;font-size:13px;margin-bottom:6px;">' . htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') . '</div>' : '')
        . '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">'
            . ($showPrice ? '<span style="font-weight:700;color:#065f46;">' . htmlspecialchars($priceText, ENT_QUOTES, 'UTF-8') . '</span>' : '<span></span>')
            . '<form method="post" action="shop.php" style="display:flex;align-items:center;gap:8px;margin:0;">'
                . '<input type="hidden" name="form_action" value="add_to_cart">'
                . '<input type="hidden" name="product_id" value="' . $productId . '">'
                . $returnField
                . '<input type="number" name="quantity" value="1" min="1" style="width:64px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 8px;font-size:14px;">'
                . '<button type="submit" style="background:#2563eb;color:white;border:none;border-radius:6px;padding:8px 12px;font-size:14px;cursor:pointer;">Add to cart</button>'
            . '</form>'
        . '</div>'
        . '</div>';
}

// Parse key="value" pairs from a shortcode string
function parse_shortcode_attributes(string $text): array
{
    $attrs = [];
    if (preg_match_all('/(\\w+)\\s*=\\s*"([^"]*)"/', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $attrs[strtolower($m[1])] = $m[2];
        }
    }
    return $attrs;
}

// Replace [product ...] tokens with product embeds. Supports attributes:
// slug (required), show="name,price,category,description"
function render_content_with_products(string $html): string
{
    $categoryLookup = [];
    $categories = load_categories();
    foreach ($categories as $cat) {
        $categoryLookup[$cat['id']] = $cat['name'];
    }

    return preg_replace_callback('/\\[product\\s+([^\\]]+)\\]/i', function ($matches) use ($categoryLookup) {
        $attrString = $matches[1];
        $attrs = parse_shortcode_attributes($attrString);
        $slug = trim($attrs['slug'] ?? '');
        if ($slug === '') {
            return '';
        }
        $product = find_product_by_slug($slug);
        if (!$product) {
            return '<div class="embedded-product missing" style="border:1px dashed #f87171;border-radius:8px;padding:12px;margin:12px 0;color:#b91c1c;font-size:14px;">Product not found: ' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $show = null;
        if (!empty($attrs['show'])) {
            $show = array_values(array_filter(array_map('trim', explode(',', $attrs['show'])), 'strlen'));
        }
        $returnUrl = $_SERVER['REQUEST_URI'] ?? null;
        $options = [];
        if ($show) {
            $options['show'] = $show;
        }
        return render_product_embed($product, $returnUrl, $options, $categoryLookup);
    }, $html);
}

// --- Shop menu helpers ---

function load_shop_menu_items(): array
{
    $db = get_db();
    $stmt = $db->query('SELECT id, label, url, sort_order, open_new_tab, created_at, updated_at FROM shop_menu_items ORDER BY sort_order ASC, label COLLATE NOCASE');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function save_shop_menu_item(array $data): void
{
    $db = get_db();
    $now = date('c');
    $stmt = $db->prepare('INSERT INTO shop_menu_items (id, label, url, sort_order, open_new_tab, created_at, updated_at)
                          VALUES (:id, :label, :url, :sort_order, :open_new_tab, :created_at, :updated_at)
                          ON CONFLICT(id) DO UPDATE SET
                            label = excluded.label,
                            url = excluded.url,
                            sort_order = excluded.sort_order,
                            open_new_tab = excluded.open_new_tab,
                            updated_at = excluded.updated_at');
    $stmt->execute([
        ':id' => $data['id'] ?? generate_entity_id('smenu_'),
        ':label' => $data['label'],
        ':url' => $data['url'],
        ':sort_order' => (int)($data['sort_order'] ?? 0),
        ':open_new_tab' => !empty($data['open_new_tab']) ? 1 : 0,
        ':created_at' => $data['created_at'] ?? $now,
        ':updated_at' => $now,
    ]);
}

function delete_shop_menu_item(string $id): void
{
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM shop_menu_items WHERE id = :id');
    $stmt->execute([':id' => $id]);
}
