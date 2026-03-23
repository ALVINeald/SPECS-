# SPECS – Supermarket Pricing Estimation & Comparison System

> A web-based price comparison system for supermarkets in Mbarara City, Uganda.

**Student:** Mbabazi Alvin | **Reg No:** 24/BSU/DIT/3253  
**Department:** Computing, Library & Information Technology  
**Institution:** Bishop Stuart University, Mbarara  
**GitHub:** [github.com/Alvineald/specs-mbarara](https://github.com/Alvineald/specs-mbarara)

---

## What is SPECS?

SPECS helps shoppers in Mbarara City compare grocery prices across 7 supermarkets, track price trends, set price alerts, and plan the most cost-effective shopping route — all in one free web application.

---

## Features

### Consumer Features
- **Price Comparison** — Compare 205+ products across 7 stores instantly
- **Smart Basket** — Add items and see which store gives the cheapest total
- **Smart Shopping Route** — Map showing which store to visit for each item at the lowest price (powered by Leaflet.js + OpenStreetMap)
- **Price Alerts** — Set a target price; get notified when a product drops below it
- **Price Trends** — View price history charts for any product
- **Shopping Plan** — Save and print a shareable receipt like an MTN MoMo receipt
- **Budget Tracker** — Set monthly grocery budget and track spending

### Admin Features
- **Dashboard** — Live stats, recent price changes, activity log
- **Product Management** — Add, edit, delete products
- **Price Management** — Update prices across all stores
- **Store Management** — Manage all 7 supermarket profiles
- **User Management** — View, activate/deactivate user accounts
- **Alerts Management** — Monitor all user price alerts
- **Reports** — Weekly price audit, price differences, user report (downloadable CSV)

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.x |
| Database | MySQL (MariaDB via XAMPP) |
| Frontend | HTML5, CSS3, JavaScript |
| Maps | Leaflet.js + OpenStreetMap |
| Charts | Chart.js |
| Server | XAMPP (Apache + MySQL) |
| Fonts | Google Fonts (Nunito, Nunito Sans) |

---

## Database

**Database name:** `specs_db`

| Table | Records |
|-------|---------|
| products | 205 |
| stores | 7 |
| prices | 1,385 |
| users | 6 (test accounts) |
| categories | 11 |
| alerts | — |
| basket | — |
| store_plans | — |
| price_history | — |
| admin_logs | — |
| password_resets | — |

---

## The 7 Stores

| # | Store | Location | Tier |
|---|-------|----------|------|
| 1 | FRESCO Supermarket | Buremba Road | Premium |
| 2 | Kirimi Supermarket | Buremba Road | Mid |
| 3 | Day to Day Supermarket | High Street | Budget |
| 4 | Apple Door to Door | Bananuka Drive | Mid |
| 5 | Amazon Express | High Street | Budget |
| 6 | Golf Course Supermarket | Lower Circular Road | Premium |
| 7 | Mbarara Central Market | Buremba Road | Market |

---

## Installation (Localhost)

### Requirements
- XAMPP (Apache + MySQL)
- PHP 8.0+
- Web browser (Chrome, Firefox, Edge)

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/Alvineald/specs-mbarara.git
```

**2. Move to XAMPP htdocs**
```
C:\xampp\htdocs\specs\
```

**3. Start XAMPP**
- Open XAMPP Control Panel
- Start Apache and MySQL

**4. Create the database**
- Go to `http://localhost/phpmyadmin`
- Create database named `specs_db`
- Import `database/specs_db.sql`
- Import `database/specs_seed_v2.sql`

**5. Set passwords**
- Place `generate_passwords.php` in `C:\xampp\htdocs\`
- Visit `http://localhost/generate_passwords.php`
- Copy and run the SQL UPDATE statements in phpMyAdmin
- Delete `generate_passwords.php` immediately after

**6. Open the system**
```
http://localhost/specs/
```

---

## Login Credentials (Test Accounts)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@specs.ug | admin123 |
| Admin | alvin@specs.ug | specsmbarara2025 |
| Manager | manager@specs.ug | manager123 |
| Consumer | sarah@gmail.com | user123 |
| Consumer | john@yahoo.com | user123 |
| Consumer | grace@gmail.com | user123 |

---

## Folder Structure

```
specs/
├── config/
│   └── db.php                  ← Database connection
├── includes/
│   ├── header.php              ← Navigation + global styles
│   ├── footer.php              ← Footer
│   ├── auth.php                ← Login/register/logout logic
│   └── functions.php           ← Helper functions
├── database/
│   ├── specs_db.sql            ← Table structure
│   └── specs_seed_v2.sql       ← Sample data
├── admin/                      ← Admin panel (7 pages)
├── user/                       ← Consumer panel (6 pages + route)
├── api/                        ← AJAX endpoints (9 files)
├── assets/
│   ├── css/                    ← style.css, admin.css, user.css
│   ├── js/                     ← main.js, basket.js, charts.js, alerts.js
│   └── images/                 ← logo, favicon, store photos
├── index.php                   ← Public homepage
├── login.php                   ← Login page
├── register.php                ← Registration + Terms & Conditions
├── logout.php                  ← Logout handler
└── forgot.php                  ← Password reset
```

---

## Design System

```css
--forest : #18382a   /* Primary dark green */
--leaf   : #2d6a4f   /* Medium green */
--mint   : #52b788   /* Light green accent */
--gold   : #e9a820   /* Gold accent */
--cream  : #fdf8f2   /* Background */
--sand   : #e8e2d9   /* Borders */
--ink    : #1c1a17   /* Body text */
--muted  : #7a7060   /* Muted text */
```

---

## Developer

**Mbabazi Alvin**  
Diploma in Information Technology  
Department of Computing, Library & Information Technology  
Bishop Stuart University, Mbarara, Uganda  
Student No: 24/BSU/DIT/3253

---

*SPECS — Supermarket Pricing Estimation & Comparison System*  
*© 2026 Mbabazi Alvin — Bishop Stuart University*
