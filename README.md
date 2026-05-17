# AssetFlow — PHP Inventory Management System

A complete asset management system with **Stock In** and **Stock Out** modules, built with vanilla PHP + MySQL (PDO). No frameworks required.

---

## Features

| Module | Capabilities |
|---|---|
| **Dashboard** | Live KPIs, low-stock alerts, recent transactions |
| **Asset Registry** | Add / edit / deactivate assets, auto-generated asset codes |
| **Stock In** | Record incoming goods, auto-increments stock qty, reference numbers |
| **Stock Out** | Issue assets with recipient details, guards against insufficient stock |
| **Categories** | Group assets, prevent deletion if assets are linked |
| **Reports** | Date-range movement report, filterable by type and asset |

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Web server: Apache with `mod_rewrite` or Nginx

---

## Setup

### 1. Configure Database

Edit `includes/db.php` and set your credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
define('DB_NAME', 'inventory_db');
```

### 2. Create Database & Tables

Import the schema (includes sample seed data):

```bash
mysql -u root -p < schema.sql
```

Or paste the contents of `schema.sql` directly into phpMyAdmin.

### 3. Deploy Files

Place the entire `inventory/` folder inside your web server root:

```
/var/www/html/inventory/   (Apache)
/usr/share/nginx/html/inventory/   (Nginx)
```

### 4. Open in Browser

```
http://localhost/inventory/
```

---

## File Structure

```
inventory/
├── index.php            # Dashboard
├── assets.php           # Asset Registry (CRUD)
├── stock_in.php         # Stock In module
├── stock_out.php        # Stock Out module
├── categories.php       # Category management
├── reports.php          # Movement report
├── schema.sql           # Database schema + seed data
└── includes/
    ├── db.php           # PDO connection
    ├── helpers.php      # Utility functions
    ├── header.php       # Layout header + sidebar + CSS
    └── footer.php       # Layout footer + JS
```

---

## Security Notes

- All user inputs are passed through PDO prepared statements (no SQL injection).
- Output is escaped with `htmlspecialchars()` via the `sanitize()` helper.
- For production, add: session-based authentication, CSRF tokens on POST forms, and HTTPS.

---

## Customisation

- **Currency symbol**: Change `₱` in `includes/helpers.php` → `formatMoney()`.
- **Asset code prefix**: Change `AST-` in `includes/helpers.php` → `generateAssetCode()`.
- **Per-page limits**: Adjust `LIMIT 200` in the SQL queries in each module file.
