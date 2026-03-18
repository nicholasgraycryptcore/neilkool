<?php
function admin_nav_items(): array
{
    return [
        ['label' => 'Dashboard', 'url' => 'admin.php', 'key' => 'dashboard'],
        ['label' => 'Cashier', 'url' => 'pos.php', 'key' => 'pos'],
        ['label' => 'Orders', 'url' => 'orders.php', 'key' => 'orders'],
        ['label' => 'Ecommerce', 'url' => 'ecommerce.php', 'key' => 'ecommerce'],
        ['label' => 'Suppliers', 'url' => 'suppliers.php', 'key' => 'suppliers'],
        ['label' => 'Purchase Orders', 'url' => 'purchase_orders.php', 'key' => 'purchase_orders'],
        ['label' => 'Expenses', 'url' => 'expenses.php', 'key' => 'expenses'],
        ['label' => 'Reports', 'url' => 'reports.php', 'key' => 'reports'],
        ['label' => 'Exports', 'url' => 'exports.php', 'key' => 'exports'],
        ['label' => 'Users', 'url' => 'users.php', 'key' => 'users'],
        ['label' => 'Backups', 'url' => 'backup.php', 'key' => 'backups'],
        ['label' => 'Media', 'url' => 'media.php', 'key' => 'media'],
        ['label' => 'Site settings', 'url' => 'site_settings.php', 'key' => 'settings'],
        ['label' => 'Reusable parts', 'url' => 'parts.php', 'key' => 'parts'],
        ['label' => 'Code snippets', 'url' => 'snippets.php', 'key' => 'snippets'],
        ['label' => 'Shop', 'url' => 'shop.php', 'key' => 'shop'],
    ];
}

function render_admin_sidebar(string $activeKey): void
{
    $items = admin_nav_items();
    ?>
    <style>
        .admin-shell {
            display: flex;
            min-height: 100vh;
            background: #f1f5f9;
        }
        .admin-sidebar {
            width: 240px;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            transition: width 0.2s ease;
        }
        .admin-sidebar.collapsed {
            width: 72px;
        }
        .admin-sidebar .brand {
            padding: 16px;
            font-weight: 700;
            font-size: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .admin-sidebar nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: inherit;
            text-decoration: none;
            border-radius: 8px;
            margin: 4px 8px;
            transition: background 0.15s;
        }
        .admin-sidebar nav a:hover {
            background: rgba(255,255,255,0.08);
        }
        .admin-sidebar nav a.active {
            background: rgba(59,130,246,0.2);
            color: #bfdbfe;
        }
        .admin-sidebar .collapse-btn {
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 12px;
        }
        .admin-main {
            flex: 1;
            padding: 20px;
        }
        .admin-sidebar.collapsed .label {
            display: none;
        }
    </style>
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById('admin-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('adminSidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
            }
        }
        document.addEventListener('DOMContentLoaded', function(){
            var sidebar = document.getElementById('admin-sidebar');
            if (sidebar && localStorage.getItem('adminSidebarCollapsed') === '1') {
                sidebar.classList.add('collapsed');
            }
        });
    </script>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="brand">
                <span>Admin</span>
                <button type="button" class="collapse-btn" onclick="toggleSidebar()">☰</button>
            </div>
            <nav class="flex-1 overflow-y-auto py-2">
                <?php foreach ($items as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $activeKey === $item['key'] ? 'active' : ''; ?>">
                        <span class="label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="p-3 border-t border-slate-700">
                <a href="login.php?logout=1" class="text-sm text-rose-200 hover:text-rose-100">Logout</a>
            </div>
        </aside>
        <div class="admin-main">
    <?php
}

function render_admin_sidebar_close(): void
{
    echo '</div></div>';
}
