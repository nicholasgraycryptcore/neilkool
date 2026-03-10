# Ecommerce Admin & Embeds Guide

## Where to manage products
- Go to `ecommerce.php` (Admin → Ecommerce) to create products and categories/subcategories.
- Fields per product: name, slug (URL-friendly, optional), SKU (optional), price, currency, initial stock, category/subcategory, status (active/inactive), description, primary image URL, optional gallery image URLs (comma separated).
- Stock updates:
  - Set initial stock on create.
  - Use “Inventory adjustment” (same page) to add/remove units with a reason; adjustments log to `inventory_movements` and `audit_logs`.

Images:
- Primary image shows on product cards, embeds, product detail pages, and POS cart rows.
- Gallery images show on product detail pages (product.php) and are auto-inserted into the generated product page content.

## Cashier / POS
- Go to `pos.php` (Admin → Cashier).
- Add products to the cart, set quantities, apply discount/tax, choose payment method, and complete sale.
- Completing a sale:
  - Creates an order with source `pos`.
  - Records a payment.
  - Decrements inventory for each line.
  - Logs the action to `audit_logs`.

## Storefront (public)
- Public shop at `shop.php`.
- Customers can add to cart, update/remove/clear, and checkout with name/contact (optional).
- Checkout:
  - Creates an order with source `storefront`.
  - Decrements inventory.
  - Logs the action.

## Embedding products on pages
- Use shortcodes inside page content (pages managed in `admin.php`).
- Syntax: `[product slug="your-product-slug" show="name,price,category,description"]`
  - `slug` (required): product slug from the catalog.
  - `show` (optional): comma-separated fields to display. Defaults to `name,description,price,category`.
    - Allowed: `name`, `price`, `category`, `description`.
- Behavior:
  - Renders a product card with an add-to-cart form (posts to `shop.php` and returns to the same page).
  - Respects product status and stock; shows “Out of stock” when unavailable.
  - Primary image renders on the card when present.

## Data & helpers (in `config.php`)
- Tables: `products`, `categories`, `inventory_movements`, `orders`, `order_items`, `payments`, `audit_logs`.
- Helpers:
  - `save_product()`, `save_category()`, `load_products()`, `load_categories()`
  - `adjust_inventory()` — stock changes with logging.
  - `create_order()` — builds orders + items; decrements stock when requested.
  - `record_payment()` — adds a payment and marks order paid when fully covered.
  - `render_content_with_products()` — processes page content shortcodes for embeds.
  - `ensure_product_page()` — auto-generates a product page (slug `product-{product_slug}`) with embeds/gallery if missing.

## Logging
- Key actions log to `audit_logs`: product/category saves, inventory adjustments, POS checkout, storefront cart/checkout, product embeds interactions (cart add/update/remove/clear, checkout).

## Quick workflows
1) Create categories → create products (set status to active) → set stock via “Inventory adjustment”.
2) Use POS (`pos.php`) to ring up sales; inventory and orders auto-updated.
3) For public sales, point customers to `shop.php`.
4) To feature products on any page, insert `[product slug="..."]` shortcodes; adjust `show` fields as needed.
5) Each product auto-generates a page at slug `product-{slug}`; edit it in the regular Pages admin if you want custom long-form content. Product cards and shop listings link to `product.php?slug={slug}`.
