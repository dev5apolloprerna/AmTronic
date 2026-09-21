# Vendor / Customer Quotation & Invoice Manager

A Laravel + MySQL web application to manage customers (with ledger/due tracking), a product master,
role-based users, quotations (with multi-product line items and GST), invoice generation on approval,
and reporting (customer ledger history + sales report).

## Tech Stack
- Laravel 11 (PHP 8.2+)
- MySQL
- Plain CSS/JS (no build step) — see `public/css/` and `public/js/`
- barryvdh/laravel-dompdf for invoice PDF generation

## Features

### Roles
- **Super Admin** — manages Customers, Products, Users, Number Settings (prefix master), and views Reports.
- **User** — created by Super Admin, can create/edit quotations (while draft), approve them, and record
  customer ledger payments.

### Customers (formerly "Vendor")
- Full CRUD (Super Admin only).
- `opening_balance` is a single **signed** value: positive = customer owes us (due), negative = advance
  they already have with us. No separate debit/credit type needed.
- Ledger entries (`customer_ledgers`) can be added by **either** a User or Super Admin — the `entered_by`
  column always records who made the entry.
- A running `balance_after` is stored on every ledger row so the ledger history is always reconcilable.

### Product Master
- Full CRUD (Super Admin only): name, code, unit (default "Mtr"), HSN code, status.

### Quotations
- A quotation belongs to one Customer and has many line items (products).
- Each line item captures: Product, Size (Mtr) per roll, Number of Rolls, Price per Mtr.
  `Total Mtr = Size x Rolls`, `Amount = Total Mtr x Price`.
- **Auto last-price fill**: when a Customer + Product are selected, an AJAX call
  (`GET /ajax/last-price?customer_id=&product_id=`) looks up the price used in that customer's most
  recent *approved* quotation for the same product and pre-fills it (user can still override it).
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
   - Sample User login: `user@example.com` / `password`
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
  (add/remove product lines, live totals, GST calculation, and the last-price AJAX lookup).
- No Vite/Node build step is required — assets are plain static files served directly.

## Database Schema Summary
See migrations in `database/migrations/` for the authoritative schema:
`users`, `customers`, `customer_ledgers`, `products`, `number_settings`, `quotations`,
`quotation_items`, `invoices`.
# glass-grip
# AmTronic
# AmTronic
