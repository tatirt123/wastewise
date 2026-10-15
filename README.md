# WasteWise

WasteWise is a PHP and MySQL web application for waste segregation and local waste-management services. It provides waste-disposal guidance, collection schedules, illegal-dumping reports, and separate dashboards for residents, collectors, and administrators.

## Features 

- Account registration, login, and role-based page routing
- Waste search and segregation guidance for biodegradable, recyclable, residual, and hazardous items
- Resident dashboard with collection schedules, recycling information, community activities, announcements, and impact statistics
- Illegal-dumping report management for residents, collectors, and administrators
- Profile and avatar management
- Administrative management of reports and application data

## Requirements

- PHP with PDO and the PDO MySQL driver
- MySQL or MariaDB
- A modern web browser
- Internet access for the Tailwind CSS, Font Awesome, and Google Fonts assets loaded from CDNs

## Local setup

1. Start your MySQL or MariaDB server.
2. Configure the database connection in [`config/db.php`](config/db.php). The current defaults target a local MySQL server, database `tatang`, and the local `root` account. Change these values to match your environment.
3. From the project root, start PHP's built-in development server:

   ```powershell
   php -S localhost:8000
   ```

4. Open `http://localhost:8000/config/setup_db.php` in your browser once to create and seed the database tables.
5. Open `http://localhost:8000/` to use the application.

The setup script creates the schema and inserts sample data into tables that are empty. Do not expose it on a public production server. For a production deployment, use a dedicated database account, set strong credentials outside source control, and remove or restrict access to the setup script.

`config/tatang_schema.sql` is also provided as a schema/data dump. It contains `DROP TABLE IF EXISTS` statements, so importing it into a database with existing data can delete those tables and their contents. Back up your database before using it.

## Main pages

| Page | Purpose |
| --- | --- |
| [`index.php`](index.php) | Landing page and sign-in/sign-up |
| [`dashboard.php`](dashboard.php) | Resident dashboard |
| [`collector.php`](collector.php) | Waste collector dashboard |
| [`admin.php`](admin.php) | Administrator dashboard |

The `login.php`, `dasboard.php`, `ind2.php`, and `ind3x.php` files are compatibility aliases that load the corresponding PHP pages.

## Project structure

```text
.
├── api/                  JSON endpoints for authentication, profiles, reports, schedules, and more
├── config/
│   ├── db.php            PDO connection and session helpers
│   ├── setup_db.php      Database initialization and sample-data seeder
│   └── tatang_schema.sql SQL schema/data dump
├── uploads/              Runtime user uploads; ignored by 
├── admin.php
├── collector.php
├── dashboard.php
├── index.php
├── script.js
└── style.css
```

## Notes

- Uploaded avatars and report files are stored under `uploads/`, which is excluded from version control.
- The application uses PHP sessions for authentication.
- The provided PHP built-in server is for local development only; use a properly configured web server and PHP runtime for deployment.
