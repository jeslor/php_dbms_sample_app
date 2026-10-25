# 🛠️ Trust Hardware — Shop Management System

A small hardware-store point-of-sale and inventory app, built with plain **PHP + MySQL + vanilla JS**. No frameworks, no build step, no Composer — just the fundamentals, which makes it a great project to read end-to-end if you're learning PHP and database-backed web apps.

You'll find two "sides" to the app:

- **Admin** — manages inventory, categories, employees, sales history, receipt settings, and sees profit.
- **Employee** — a point-of-sale (POS) screen for ringing up sales, checking stock, and reprinting receipts.

If you're a student poking around this code, the goal is for you to be able to **run it in 10 minutes**, log in as both roles, sell something, and then go read the PHP files knowing exactly what each one is responsible for.

---

## 🧠 How it works (the big picture)

Think of the app as a handful of PHP scripts that each do one job. There's no router, no MVC framework — every page is just a `.php` file you can open directly.

```
index.php          → the login screen (role: admin or employee)
authenticate.php   → verifies the password, starts the session, redirects
logout.php         → destroys the session

admin.php          → the whole admin dashboard (HTML shell + nav)
admin_api.php      → JSON API the admin dashboard calls (inventory, sales, employees...)

employee.php       → the point-of-sale screen (HTML shell + nav)
employee_api.php   → JSON API the POS screen calls (sell, check stock, receipts...)

public_api.php     → the ONE endpoint usable before login (lists employee names for the dropdown)

config/db.php      → database connection + session setup, loaded by every page
schema.sql         → creates the database, tables, and demo data
assets/            → CSS (print.css for receipts) and JS (admin.js, employee.js)
```

### The request flow, step by step

1. **You visit `index.php`.** It shows a login form: pick a role (Admin or Employee), and if Employee, pick *which* employee from a dropdown. That dropdown is filled by a `fetch()` call to `public_api.php?action=listEmployees` — notice this is the *only* page unauthenticated visitors are allowed to query, and it deliberately returns just names, never password hashes.

2. **You submit the form to `authenticate.php`.** This script:
   - Looks up the user in the `users` table.
   - Checks the password with PHP's `password_verify()` (passwords are stored as bcrypt hashes via `password_hash()` — **never** in plain text).
   - Tracks failed attempts in `$_SESSION` and locks the login out after 5 bad tries.
   - On success, calls `session_regenerate_id(true)` (a security habit: never keep using the same session ID a user had *before* they proved who they are) and stores `user_id`, `username`, `full_name`, and `role` in `$_SESSION`.
   - Redirects to `admin.php` or `employee.php` depending on role.

3. **`admin.php` / `employee.php` are the "shell" pages.** Each one is a single HTML page with a sidebar and several `<div class="section">` blocks (Dashboard, Inventory, Sales, etc. for admin; POS, Stock, Sales History for employee). A tiny bit of JS (`showSection()`) just toggles which `<div>` is visible — there's no page reload when you click between tabs.

4. **The real work happens over JSON.** Each shell page loads a matching JS file (`assets/js/admin.js` or `assets/js/employee.js`). That JS calls `admin_api.php?action=...` or `employee_api.php?action=...` using `fetch()`, gets JSON back, and updates the DOM. This is the same pattern modern single-page apps use, just without a framework — a good way to *see* how `fetch` + JSON + vanilla DOM updates fit together before you ever touch React or Vue.

5. **Every API file guards itself.** The very first thing `admin_api.php` and `employee_api.php` do is check `$_SESSION['role']` and immediately return a `401` if you're not logged in as the right role. This is the core lesson here: **never trust the client** — the sidebar hides admin links from employees, sure, but the *real* security check happens again on the server, every single request.

### The database, in one paragraph

`schema.sql` creates six tables: `users` (admins + employees, with bcrypt password hashes), `categories`, `products` (with `stock`, `cost_price`, `min_price`/`max_price`), `sales` (one row per line item, with `transaction_code` grouping items from the same checkout), `settings` (store name, tax rate, receipt footer — key/value pairs), and `reset_requests` (reserved for future use). Selling something in `employee_api.php` does three things inside **one database transaction**: decrements `products.stock`, inserts a `sales` row, and snapshots the product's cost price so profit stays correct even if the cost changes later. If anything fails mid-sale, the whole transaction rolls back — nothing gets half-saved. That's a nice real-world example of *why* transactions exist.

### A couple of neat details worth noticing while you read the code

- **Admins see profit, employees don't.** `employee_api.php`'s `getInventory` query *literally does not select* `cost_price` — it's not just hidden by CSS, it never leaves the database for that request. Compare that to `admin_api.php`'s version of the same query.
- **Receipts are just `@media print` CSS.** Look at `assets/css/print.css` — there's no PDF library involved. The browser's own print dialog renders the receipt.
- **Timezone handling.** `config/db.php` pins both PHP and MySQL to `Africa/Kampala` (UTC+3) explicitly, so "today's sales" means the same thing regardless of what timezone the server happens to be configured with.

---

## 🚀 Running it locally

You'll need a local **PHP + MySQL** environment. The friendliest way to get both at once is a bundle like **XAMPP**, **MAMP**, or **Laragon** — pick whichever matches your OS. If you already have PHP (8.x) and MySQL/MariaDB installed separately, that works too.

### 1. Get the code onto your machine

```bash
git clone <this-repo-url>
cd trust_hardware
```

(Or just download/copy the folder if you're not using Git yet.)

### 2. Create the database

Open your MySQL client of choice — phpMyAdmin, the `mysql` CLI, TablePlus, whatever you're comfortable with — and run `schema.sql`.

**Using the terminal:**

```bash
mysql -u root -p < schema.sql
```

**Using phpMyAdmin:** open the *SQL* tab on any database, paste the contents of `schema.sql`, and click *Go*.

This single file does everything: creates the `trust_hardware_db` database, builds all six tables, and seeds it with a demo admin account, 8 categories, and 15 sample products — so you've got real data to click around with immediately.

### 3. Point the app at your database

Open `config/db.php` and check these lines match your MySQL setup:

```php
$host = 'localhost';
$dbname = 'trust_hardware_db';
$username = 'root';
$password = '';
```

If your MySQL root user has a password (common on MAMP/Linux installs), set it here. Everything else can stay as-is for local development.

### 4. Start the PHP server

If you're using XAMPP/MAMP/Laragon, drop the project folder inside their `htdocs`/`www` directory and start Apache + MySQL from their control panel, then skip to step 5.

Prefer the command line? PHP has a built-in dev server — no Apache needed:

```bash
php -S localhost:8000
```

### 5. Open it in your browser

Visit **http://localhost:8000** (or wherever your server is serving the folder from).

You should see the Trust Hardware login screen. 🎉

### 6. Log in and explore

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |

There's no seeded employee account yet — that's by design, so you can experience the *first real admin task* yourself:

1. Log in as **Admin**.
2. Go to **Employees** in the sidebar → add yourself as an employee (any name + a password of at least 6 characters).
3. Log out, log back in, choose **Employee** as the role, pick your name from the dropdown, and ring up a sale from the **Point of Sale** screen.
4. Go back into the Admin dashboard and watch the sale show up in **Sales History** and the dashboard metrics update in real time.

That one loop — create an employee, make a sale, see it reflected in the data — touches almost every table and every file in the project. It's the best way to build a mental model of the whole system fast.

---

## 🔍 Suggested reading order

If you want to study the code rather than just run it, read the files in this order — it mirrors how a request actually flows through the app:

1. `schema.sql` — know the data shape first.
2. `config/db.php` — see how the connection and session get set up.
3. `index.php` → `authenticate.php` — the login flow.
4. `public_api.php` — the smallest API file, good for seeing the JSON pattern in miniature.
5. `employee.php` + `assets/js/employee.js` + `employee_api.php` — the simpler of the two dashboards.
6. `admin.php` + `assets/js/admin.js` + `admin_api.php` — the full-featured one.

Happy hacking! 🧰
