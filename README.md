# RAPID — Repair Assessment, Progress, and Issue Documentation

**Online Device Repair Ticketing and Progress Monitoring System**

Shop branding: **RAPID Device Care**

This repository currently contains the **foundation scaffold**: database schema, authentication, role-based portals (stubs), public landing page, and public ticket tracking. Repair booking, quotations, warranty workflows, and full dashboards will be added in later steps.

---

## Requirements

- WAMP / XAMPP (Apache + PHP **7.4+** or **8.x** + MySQL)
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`
- Browser with JavaScript enabled (SweetAlert2 via CDN)
- Node.js 18+ (optional, only to rebuild Tailwind CSS after UI class changes)

> Tip: If WAMP’s PHP version menu includes 8.x, you can switch to it; the foundation also runs on PHP 7.4.

---

## Installation

1. Place the project under your web root (already at `c:\wamp64\www\RAPID` for WAMP).
2. Start **Apache** and **MySQL** in WAMP.
3. Open **phpMyAdmin** → Import [`database/rapid.sql`](database/rapid.sql) (schema + login accounts).
   Then import [`database/seed.sql`](database/seed.sql) for the full demo dataset (tickets in every status, quotations, warranties, claims).
   - Or from a shell:
     ```bash
     mysql -u root < database/rapid.sql
     mysql -u root rapid < database/seed.sql
     ```
   Re-importing `seed.sql` deletes existing RAPID rows and reloads demo data.
   In phpMyAdmin, select the `rapid` database first and uncheck **Enable foreign key checks**.
4. Confirm database credentials in [`config/database.php`](config/database.php) (default WAMP: user `root`, empty password).
5. Open: [http://localhost/RAPID/](http://localhost/RAPID/)

Pages use extensionless URLs (`/admin/customer` instead of `/admin/customer.php`). Apache `mod_rewrite` must be enabled (default on WAMP). Old `.php` links redirect to the clean URL.

Compiled CSS ships as [`assets/css/app.css`](assets/css/app.css). After changing Tailwind classes, rebuild with:

```bash
npm install
npm run build:css
```

---

## Demo accounts

| Role        | Email                  | Password       |
|-------------|------------------------|----------------|
| Admin       | `admin@rapid.local`    | `Admin@123`    |
| Technician  | `tech@rapid.local`     | `Tech@123`     |
| Technician  | `tech2@rapid.local`    | `Tech@123`     |
| Technician  | `tech3@rapid.local`    | `Tech@123`     |
| Customer    | `customer@rapid.local` | `Customer@123` |
| Customer    | `customer2@rapid.local`| `Customer@123` |
| Customer    | `customer3@rapid.local`| `Customer@123` |
| Customer    | `customer4@rapid.local`| `Customer@123` |
| Customer    | `customer5@rapid.local`| `Customer@123` |

`tech2` / `tech3` and `customer2`–`customer5` exist after importing [`database/seed.sql`](database/seed.sql).

Demo public ticket: **`RPR-2026-000001`** (status: Received)

---

## What works in this foundation

- Customer registration (creates `users` + `customers`)
- Login / logout with hashed passwords and CSRF protection
- Role redirects: admin → `/admin/`, technician → `/technician/`, customer → `/customer/`
- Session inactivity timeout (30 minutes)
- Landing page with **Track My Repair**
- Public [`track.php`](track.php) — limited status/timeline (no PII, no private media/notes)
- Full relational MySQL schema + seed data
- **Customer repair booking** with device details, before-repair media uploads, auto ticket numbers
- **My Repairs** list (search/filter/pagination) and **ticket detail** with progress tracker
- **Admin ticket management** — search/filter, assign technician, validated status updates
- **Technician workspace** — assigned queue, diagnosis, quotations (server-side totals), status updates, repair media
- **Customer quotation approve / decline** with notifications
- **Warranty on completion** (30-day default, dynamic remaining/status) and **warranty claims** (customer file + staff process)
- **Admin customer & technician CRUD** (search, add, edit, activate/deactivate)
- **Reports** with Chart.js charts from live database data
- **Live notification dropdown** (API + mark read / mark all)
- **Technician AI repair assistant** (Gemini) — likely causes, tests, and diagnosis drafts from the current ticket
- **Admin Settings** — paste a free Gemini API key and choose the model

## AI repair assistant

1. Create a free Gemini key at [Google AI Studio](https://aistudio.google.com/apikey) (no credit card).
2. Sign in as admin → **Settings** → paste the key. Default model: `gemini-flash-lite-latest` (free-tier Flash is often overloaded; Lite has more capacity).
3. Open an assigned ticket as a technician → **Suggest repair steps**.

WAMP Apache often has no SSL CA file, which shows as a network error. RAPID ships `config/cacert.pem` and uses it for Gemini HTTPS.

Existing databases pick up the AI tables automatically when you open Settings or a technician ticket.

The assistant is ticket-specific (device, problem, photos, similar RAPID jobs). Customer name, phone, email, address, IMEI, and serial are not sent to Google.

## Not yet built (optional polish)

- Customer/technician profile self-edit UI
- Email/SMS gateways for notifications

---

## Project structure

```text
RAPID/
├── config/           # App + database config
├── includes/         # Auth, CSRF, helpers, layout
├── assets/           # CSS / JS
├── uploads/          # Device & repair media (script execution blocked)
├── auth/             # Login, register, logout
├── admin/            # Admin portal (stub dashboard)
├── technician/       # Technician portal (stub dashboard)
├── customer/         # Customer portal (stub dashboard)
├── api/              # Reserved for later endpoints
├── database/         # SQL schema + seeds
├── index.php         # Landing page
└── track.php         # Public ticket tracking
```

---

## Security notes

- Passwords use `password_hash()` / `password_verify()`
- SQL via PDO prepared statements
- Output escaped with `e()`
- CSRF tokens on auth forms and JSON APIs
- AI prompts omit customer PII (name, phone, email, address, IMEI, serial)
- Upload directories block executable scripts
- Public tracking exposes only non-sensitive ticket fields

---

## Acronym

**RAPID** — Repair Assessment, Progress, and Issue Documentation
