# Kennet Valuation — Plain PHP/MySQL System

A framework-free rebuild of the Kennet Laravel valuation app. Runs on any standard
PHP 8 + MySQL host with **cPanel**. No Composer, no CLI, no build step required.

## What's included
| File | Purpose |
|------|---------|
| `config.php` | DB credentials + app settings — **edit this first** |
| `lib.php` | DB connection, auth, lookups, number-to-words |
| `layout.php` | Shared sidebar/header shell |
| `login.php` / `logout.php` / `index.php` | Authentication |
| `bank_list.php` / `bank_form.php` | Bank valuations (list + new/edit) |
| `insurance_list.php` / `insurance_form.php` | Insurance valuations (with inspection checklist) |
| `clients.php` / `insurers.php` / `types.php` | Lookup management |
| `report.php` | Printable valuation report (Print → Save as PDF) |
| `create_user.php` | One-off admin creator — **delete after use** |
| `uploads/` | Vehicle photos & logbooks (protected from script execution) |

The app reuses your **existing database schema** — tables `bankvaluations`, `valuations`,
`clients`, `fuels`, `insurers`, `types`, `users`. Your `valuation.sql` dump imports directly,
so existing records and users carry over unchanged.

---

## Deploy to cPanel (step by step)

### 1. Create the database
cPanel → **MySQL® Databases** (or use the **MySQL Database Wizard**):
- Create a new database — cPanel prefixes it, e.g. `cpaneluser_valuation`
- Create a new user with a strong password
- **Add the user to the database** and grant **ALL PRIVILEGES**
- Note the final **DB name, user, password** (host is `localhost`)

### 2. Import your data
cPanel → **phpMyAdmin** → click the new database in the left list → **Import** tab →
choose `C:\kennet\Database\valuation.sql` → **Go**.
Then open the **SQL** tab and run the contents of `schema_patch.sql`.
*(If the .sql is larger than the web upload limit, import it in cPanel's phpMyAdmin via the
"Import" partial-import option, or ask your host to raise `upload_max_filesize`.)*

### 3. Edit `config.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_valuation');
define('DB_USER', 'cpaneluser_admin');
define('DB_PASS', 'your-db-password');
define('BASE_URL', '');   // '' if at domain root, else '/subfolder'
```
You can edit it right in cPanel **File Manager** (right-click → Edit) after uploading.

### 4. Upload the app
- cPanel → **File Manager** → open `public_html`
- **Upload** `kennet-webapp.zip`
- Select it → **Extract** → move the files into `public_html` if they extracted into a subfolder
- Set the PHP version if needed: cPanel → **Select PHP Version** → choose **PHP 8.1+**
- Ensure `uploads/` is writable: right-click → **Change Permissions** → `755` (or `775`)

### 5. Create your login (if not importing existing users)
Visit `https://yourdomain/create_user.php`, create an account, then **delete the file**.
If you imported `valuation.sql`, the existing user (`george@kennettracking.com`) already works.

### 6. Done
Go to `https://yourdomain/` → you'll be sent to the login page.

---

## Notes & differences from the Laravel version
- **PDF**: reports render as self-contained HTML; click **Print / Save as PDF** in the
  browser (Chrome/Edge "Save as PDF"). No DomPDF/Composer dependency.
- **Insurance reports**: the original app had no insurance PDF — one is included here.
- Bug fixes carried over: `chasis_damage` is now saved for insurance; number-to-words
  no longer crashes on decimal values ("Twelve" typo fixed).
- Set `display_errors` to `0` in `config.php` once everything works.
