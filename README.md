# FindIt Campus - Smart Lost and Found for Campuses

A web-based platform for campuses and organizations to report, claim, and return lost items — with admin-verified claim management.

> **SDG Alignment:** SDG 11 (Sustainable Cities) · SDG 16 (Strong Institutions)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Server | Apache (XAMPP recommended) |

---

## Installation

**1. Copy files** into your server root (`htdocs/ltms/`)

**2. Create the database**
- Open phpMyAdmin → create a database named `ltms`
- Import `ltms_database.sql`

**3. Configure `db.php`**
```php
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';        // blank for XAMPP default
$db_name = 'ltms';
```

**4. Create the uploads folder** inside the project root
```bash
mkdir uploads
```

**5. Open the app**
```
http://localhost/ltms/index.php        # Main site
http://localhost/ltms/admin_login.php  # Admin portal
```

---

## Sample Credentials

> ⚠️ For demo purposes only — change before going live.

| Role | Email / Username | Password |
|---|---|---|
| Admin | `admin@gmail.com` | `admin` |
| User | `ronjie` | `12345` |

---

## System Flow

```
Guest
 ├── Browse lost/found items
 └── Search by keyword, location, date

Registered User
 ├── Report a lost or found item (with optional photo)
 └── Submit a claim on an item
         └── Item status: Unclaimed → Pending

Admin
 ├── Review pending claims → Approve or Reject
 │       Approve → item marked Returned
 │       Reject  → item reverts to Unclaimed
 ├── Manually update item status
 ├── Delete reports
 └── View full audit log
```

---

## Item Status Lifecycle

```
Unclaimed  →  (claim submitted)  →  Pending  →  Returned
                                        └──── (rejected) ──── Unclaimed
```

---

## File Overview

| File | Purpose |
|---|---|
| `index.php` | Homepage |
| `lost.php` / `found.php` | Report forms |
| `lost_list.php` / `found_list.php` | Public item listings |
| `search.php` | Advanced search |
| `save_lost.php` / `save_found.php` | Save report to database |
| `claim_submit.php` | Submit a claim |
| `signup_login_form.php` | User login & registration |
| `admin_login.php` | Admin-only login |
| `admin_dashboard.php` | Manage claims, items, and logs |
| `admin_action.php` | Handles all admin POST actions |
| `db.php` | Database connection |
| `ltms_database.sql` | Full schema + seed data |

---

## Screenshots

> Add screenshots to a `/screenshots` folder and update the paths below.

| Page | Preview |
|---|---|
| Login page | 
<img width="1919" height="952" alt="Screenshot_12" src="https://github.com/user-attachments/assets/084cb20a-5493-4fea-a080-135e12f45d90" />

| Lost Items List | 
<img width="1233" height="881" alt="Screenshot_13" src="https://github.com/user-attachments/assets/09b4e494-8375-414d-8d7c-2b7c723c327f" /> 

| Report Form | 
<img width="1233" height="881" alt="Screenshot_13" src="https://github.com/user-attachments/assets/f87e940f-1305-44b3-ad13-d2fbaa29f174" />  <img width="1117" height="759" alt="Screenshot_14" src="https://github.com/user-attachments/assets/75f1d678-9c7e-4823-b330-f16732fd867a" />

| Admin Dashboard | 
<img width="1898" height="909" alt="Screenshot_15" src="https://github.com/user-attachments/assets/480a2bf0-e739-4a8d-a99c-9853921479a3" /> |


