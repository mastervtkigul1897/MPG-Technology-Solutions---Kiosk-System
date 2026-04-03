# Plain PHP MVC (Kiosk System)

No Composer / Laravel — runs on shared hosting with PHP 8.1+ and MySQL (phpMyAdmin).

## Roles and access

| Role | Who creates them | What they can do |
|------|------------------|------------------|
| **Super admin** | You (platform operator), via server/DB setup | Log in, open **Tenants**, create a **store (tenant)** together with the **store owner** account (name, email, password). There is **no public registration** for stores; customers contact you and you provision accounts. |
| **Store owner** (`tenant_admin`) | Super admin, when creating the tenant | Log in, manage **Staff** (create/remove **cashier** accounts), and use all store modules (ingredients, products, POS, reports, etc.). |
| **Cashier** (`cashier`) | Store owner, under **Staff** | Log in, use **Create Order**, **Transactions**, **Activity Log**, and **Profile** only to **change their own password**. Name/email are read-only. |

## Deployment (e.g. 000webhost)

1. Upload the whole project (or zip and extract on the host) — `app/`, `config/`, `views/`, `bootstrap.php`, etc. live at the archive root.
2. Point the **document root** at `public/` (on 000webhost: move `public/` contents into `public_html`, and place `app/`, `config/`, `views/`, `storage/`, `bootstrap.php` **one level above** `public_html` so application code is not web-accessible).
3. If you cannot keep `app/` outside `public_html`, use a minimal layout: `public_html/index.php` + `.htaccess` from `public/`, and adjust `public/index.php` paths to `bootstrap.php` as needed.
4. Copy `.env.example` to `.env` at the app root (next to `bootstrap.php`), then set `APP_URL` and database credentials from the hosting panel.
5. Ensure folders are `chmod 755` where needed and **`storage/` is writable** (especially `storage/rate_limit/`).
6. Import the same MySQL schema as the original Laravel app (no new migrations unless you changed the schema).

## Security (built-in)

- **SQL injection**: PDO prepared statements in controllers/services.
- **CSRF**: token on POST forms.
- **Session**: standard PHP session + login checks.
- **Rate limiting**: file-based (`storage/rate_limit/`) for login and some endpoints — not full DDoS protection (use CDN/WAF for large-scale attacks); helps against brute force and basic automated abuse.

## Local development (Mac / dev)

The PHP built-in server uses a **non-default port** (not 80). Keep the port aligned across: (1) the `php -S` command, (2) the browser URL, (3) `APP_URL` in `.env`.

```bash
cd public
php -S 127.0.0.1:8080 -t .
```

Open in the browser (same base URL):

- **http://127.0.0.1:8080** or **http://localhost:8080**
- Login: **http://localhost:8080/login**

In `.env`:

```env
APP_URL=http://localhost:8080
```

If you change the port (e.g. `8000`), update both the command and `APP_URL`. Do not use **`http://localhost/login` only** — without `:8080` the browser hits port 80 and you get **ERR_CONNECTION_REFUSED** unless another server listens there.

## Local check

```bash
php -l bootstrap.php public/index.php
```

Use `APP_DEBUG=true` only in development.
