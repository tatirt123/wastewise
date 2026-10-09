# WasteWise — Default Credentials

> [!CAUTION]
> Change these passwords before deploying to production.

---

## 👤 User Accounts

| Role | Name | Email | Password | Redirects To |
|------|------|-------|----------|--------------|
| **Admin** | Admin Officer | `admin@wastewise.com` | `admin123` | `admin.php` |
| **Collector** | Juan Dela Cruz | `juan@wastewise.com` | `admin123` | `collector.php` |
| **Citizen** | Waste Warrior | `warrior@wastewise.com` | `admin123` | `dashboard.php` |

---

## 🗄️ Database

| Setting | Value |
|---------|-------|
| **Database Name** | `tatang` |
| **Host** | `127.0.0.1` |
| **Username** | `root` |
| **Password** | *(empty)* |
| **Port** | `3306` (default) |

---

## 🌐 Local Domain

```
https://aizen4-main-project.test/
```

Hosted via **DevBoxPro** with PHP 8.4 + MySQL.

---

## 🔑 API Endpoints

| Endpoint | Method | Actions |
|----------|--------|---------|
| `api/auth.php` | POST | `login`, `signup`, `logout`, `check` |
| `api/reports.php` | GET/POST | `submit`, `list`, `update_status` |
| `api/profile.php` | POST | `update`, `get` |
| `api/search.php` | GET | `?q={keyword}` or `?cat={slug}` |
| `api/schedule.php` | GET/POST | `get`, `add` |
| `api/activities.php` | POST | `join` |
| `api/admin.php` | GET/POST | `get_users`, `update_user_role`, `get_reports`, `update_report`, `delete_report`, `get_schedules`, `add_schedule`, `update_schedule`, `get_stats` |

---

## 📁 Upload Directories

| Type | Path |
|------|------|
| Report photos | `uploads/reports/` |
| User avatars | `uploads/avatars/` |

---

## 🔄 Re-seed the Database

To reset and re-seed all tables:

```
https://aizen4-main-project.test/config/setup_db.php
```

> [!WARNING]
> This will drop and recreate all tables. All existing data will be lost.
