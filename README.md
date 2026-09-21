# Vendor / Customer Quotation & Invoice Manager

A Laravel + MySQL web application to manage customers (with ledger/due tracking), a product master,
role-based users, quotations (line items of products or materials, with GST), invoice generation on approval,
and reporting (customer ledger history + sales report).

## Tech Stack
- Laravel 11 (PHP 8.2+)
- MySQL
- Plain CSS/JS (no build step) — see `public/css/` and `public/js/`
- barryvdh/laravel-dompdf for invoice PDF generation

## Features

### Roles
- **Super Admin** — manages Customers, Products, Materials, Employees, Designations, States, Number Settings (prefix master), and views Reports.
- **User** (employee) — created by Super Admin, can create/edit quotations (while draft), approve them, and
  record customer ledger payments. **Only employees whose Designation is marked "can log in" (e.g. Sales)
  can log in**; other employees are records only (no email/password needed).

### Customers (formerly "Vendor")
- Full CRUD (Super Admin only).
- `opening_balance` is a single **signed** value: positive = customer owes us (due), negative = advance
  they already have with us. No separate debit/credit type needed.
- Ledger entries (`customer_ledgers`) can be added by **either** a User or Super Admin — the `entered_by`
  column always records who made the entry.
- A running `balance_after` is stored on every ledger row so the ledger history is always reconcilable.

### Product Master
- Full CRUD (Super Admin only): name, code, unit (default "Mtr"), HSN code (4, 6 or 8 digits), status.

### Masters (Super Admin only)
- **Material Master** — name, code, unit (default "Nos"), HSN code (required, 4, 6 or 8 digits), status.
- **Employees** (the `users` table) — each employee has an optional **Designation**; the list shows each
  employee's outstanding **advance balance** (`SUM(adv_amount) - SUM(return_amount)` from `employee_advances`)
  and whether they **can log in**.
- **Designation Master** — name, status and a **Can log in** flag. A designation assigned to employees cannot
  be deleted.
- **Login rule** — a Super Admin can always log in. An employee can only if their designation has *Can log in*
  ticked (`User::canLogin()`, checked at login; the future Android API should reuse it). For those employees
  email + password are required; for all others they are optional and no password is stored. A blank password
  on edit keeps the current one. Un-ticking the flag on a designation locks out its employees on their next
  login; an already-open session lasts until logout. An admin cannot edit their own account into one that
  can't log in.
- **State Master** — replaces the old `config/states.php` list (seeded with the same 36 entries by the migration)
  and feeds the State dropdowns on Customers and Quotation shipping. Customers/documents store the state *name*,
  so a state that is in use — and Gujarat, the home state that drives the CGST/SGST vs IGST split — cannot be
  renamed, deactivated or deleted.

### Quotations
- A quotation belongs to one Customer and has many line items.
- Each line item is: **Product / Material** (one dropdown, grouped, fed by the Product Master and the
  Material Master - active ones only), **Description** (free text, pre-filled from the master's description
  but never overwritten once the user has typed their own), **Qty** (decimals allowed, e.g. 12.5 Mtr),
  **Rate**, and `Amount = Qty x Rate`.
- The form submits the chosen item as one value, `product:<id>` or `material:<id>`, which is stored in
  `quotation_items.product_id` / `material_id` (exactly one is set).
- **Auto last-rate fill**: when a Customer + item are selected, an AJAX call
  (`GET /ajax/last-price?customer_id=&item=product:5`) looks up the rate used in that customer's most
  recent *approved* quotation for the same product or material and pre-fills it (user can still override it).
- **State**: the Shipping Address block has a State dropdown fed by the State Master. It is filled from the
  customer's state (unless "Different from customer address" is ticked) and decides the GST split:
  Gujarat = CGST + SGST, any other state = IGST.
- **Documents**: the quotation, invoice and delivery challan PDFs (and their on-screen pages) show each line's
  name, HSN, description, quantity with unit, rate and amount. The "Total Qty" row is shown only when every
  line uses the same unit.
- Quotations made before this change (roll size x rolls x price per roll) keep displaying as before: their
  size is shown next to the name and their quantity is labelled "Rolls".
- GST toggle: if enabled, a flat 18% is added to the subtotal.
- Quotations are editable while `status = draft`. Once **Approved**:
  - They become read-only (locked).
  - An Invoice is auto-generated with its own auto-numbered `invoice_number`.
  - A ledger entry is posted to the customer (increases their due amount by the invoice total).

### Invoices
- Auto-created from an approved quotation, viewable in-app, and downloadable as PDF
  (`GET /invoices/{invoice}/download`).

### Number Settings (Prefix Master)
- Super Admin can configure `prefix`, `postfix`, `next_number` and zero-padding independently for
  Quotation numbers and Invoice numbers (e.g. `QUO-2026-0001`). Numbers auto-increment on each use and
  are safely generated inside a DB transaction with row locking to avoid duplicates.

### Reports (Super Admin only)
- **Customer Ledger Report** — pick a customer + optional date range, see opening balance for the
  range, all transactions, and running balance.
- **Sales Report** — filter by date range and/or customer, see invoice totals and GST collected.

## API Documentation

The complete text reference for the sales executive API—including every URL,
request body, response example, authentication header, filter, and common error
response—is available in [`API.md`](API.md).

For local development, API URLs start with `http://127.0.0.1:8000/api`.

## Setup Instructions

1. **Install dependencies** (requires PHP 8.2+, Composer, Node not required):
   ```bash
   composer install
   ```

2. **Environment file**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure MySQL** in `.env` (already defaulted, just update credentials):
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vendor_quotation_manager
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```
   Create the database in MySQL first:
   ```sql
   CREATE DATABASE vendor_quotation_manager;
   ```

4. **Run migrations + seed default users**:
   ```bash
   php artisan migrate --seed
   ```

   This creates:
   - Super Admin login: `admin@example.com` / `password`
   - Sample User login: `user@example.com` / `password` (designation "Sales", which is allowed to log in)
   - Default number settings for Quotations (`QUO-<year>-0001`) and Invoices (`INV-<year>-0001`)

   **Change these passwords immediately after first login in production.**

5. **Serve the app**:
   ```bash
   php artisan serve
   ```
   Visit `http://127.0.0.1:8000` and log in.

## Folder Notes
- `public/css/app.css` — all application styling (no inline CSS anywhere in the Blade views).
- `public/css/invoice-pdf.css` — styling used only for the generated invoice PDF.
- `public/js/app.js` — sidebar toggle, delete-confirmation, and the dynamic quotation item builder
  (add/remove item lines, description pre-fill, live totals, GST calculation, and the last-rate AJAX lookup).
- No Vite/Node build step is required — assets are plain static files served directly.

## Database Schema Summary
See migrations in `database/migrations/` for the authoritative schema:
`users`, `customers`, `customer_ledgers`, `products`, `number_settings`, `quotations`,
`quotation_items`, `invoices`.

**`quotation_items` storage names.** The quantity is stored in `no_of_rolls` and the rate in `price_per_mtr`
(their original names, from when lines were priced per roll); `size_mtr` / `total_mtr` / `despatch_to` are only
filled on lines made before the item change. In code always use `$item->qty` / `$item->rate` (see
`App\Models\QuotationItem`) rather than the column names. They can be renamed later in a dedicated migration.

**Migration `2026_09_21_000008`** adds five columns the app already used but no migration created
(`quotations.admin_charges`, `quotations.material_handling_charges`, `invoices.other_reference`,
`invoices.admin_charges`, `invoices.material_handling_charges`). Each is guarded with `hasColumn()`, so on a
database that already has them it does nothing; on a fresh install it is what makes saving a quotation work.
# glass-grip
# AmTronic
# AmTronic
