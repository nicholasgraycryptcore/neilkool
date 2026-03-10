## PHP Website Builder

A lightweight PHP website builder (similar in spirit to WordPress, but much simpler) that lets you:

- Log into an admin area and manage pages
- Use a visual (WYSIWYG) editor with live preview
- Upload full HTML pages and tweak content
- Insert ready‑made blocks (hero, feature grid, call‑to‑action)
- Quickly edit text and links without touching HTML
- Choose simple templates/themes per page
- Store everything in a SQLite database

---

## Requirements

- PHP 8+ with PDO SQLite enabled
- No extra PHP frameworks or database server required

---

## Installation & Running

1. Place the project in a web‑accessible folder (or keep it where it is).
2. From the project folder, start the built‑in PHP server:

   ```bash
   php -S localhost:8000
   ```

3. Open your browser:
   - Public site: `http://localhost:8000/`
   - Admin login: `http://localhost:8000/login.php`

On first run, the app will create a SQLite database at `data/site.sqlite`.  
If an old `data/pages.json` file exists, it will import those pages into the database once.

---

## Default Admin Login

The default admin credentials are defined in `config.php`:

- **Username:** `admin`
- **Password:** `changeme`

You should change these values in `config.php` before deploying anywhere public:

```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'changeme');
```

---

## Main Features

- **Admin dashboard (`admin.php`)**
  - List pages, edit, delete
  - Choose a template/theme for each page (`default`, `dark`, `minimal`)
  - Visual TinyMCE editor with live preview
  - Buttons to insert ready‑made layout blocks (hero, features, CTA)
  - Optional upload of a full HTML page for advanced layouts

- **Quick text editor (`element_editor.php`)**
  - Non‑technical editing of `<p>`, `<a>`, and `<span>` content
  - Simple table to change text and link URLs without HTML

- **Public site (`index.php`)**
  - Lists all pages on the home screen
  - Renders each page’s stored HTML with the selected template styling

---

## Notes & Customization

- To add more templates/themes, extend the `$templates` array in `admin.php` and the template CSS logic in `index.php`.
- To add more ready‑made blocks, edit the `insertBlock` function in `admin.php`.
- For production use, consider:
  - Using a real TinyMCE API key instead of `no-api-key`
  - Securing the app behind HTTPS and strong credentials

