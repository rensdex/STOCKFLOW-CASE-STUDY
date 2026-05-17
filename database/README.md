# File: README.md
# Stockflow - Asset Management System

## Complete Inventory System with Stock-In/Stock-Out Modules

### Features Implemented

#### Core Modules
-  Authentication (Login/Logout with bcrypt password hashing)
-  Dashboard with real-time analytics and charts
-  Inventory Management (CRUD operations)
-  Category Management
-  Supplier Management
-  Stock-In Transactions (with auto quantity update)
-  Stock-Out Transactions (with stock validation)
-  Users & Roles Management (Admin/Staff/Viewer)
-  Audit Logs (track all user actions)
-  Notifications (real-time alerts)

#### Real-time Features (Pusher SDK)
- Instant stock quantity updates
- Live dashboard refresh
- Real-time notifications
- Low stock alerts

#### Security Features
- CSRF Protection on all forms
- SQL Injection Prevention (Prepared Statements)
- XSS Prevention (Output escaping)
- Password Hashing (Bcrypt)
- Role-based Access Control
- Session Management

#### Database Features
- Triggers for automatic stock updates
- Stored procedures
- Foreign key constraints
- Audit trail automation

### Installation Guide

1. **Setup XAMPP Environment**
   - Install XAMPP with PHP 7.4+
   - Start Apache and MySQL services

2. **Configure Database**
   - Open phpMyAdmin
   - Create database: `stockflow_db`
   - Import `database.sql` file
   - Import `sample_data.sql` for demo data

3. **Configure Environment**
   - Copy `.env.example` to `.env`
   - Update database credentials
   - Set Pusher credentials (get from pusher.com)

4. **Install Dependencies**
   - Download Pusher PHP SDK
   - Place in `pusher-php-server/` folder

5. **Configure Host**
   - Update `config.php` hostname to `casestudy`
   - Set appropriate port (3306-3310)

6. **Run Application**
   - Access via: `http://localhost:8080/index.php`
   - Default login: `maria.cruz@corp.ph` / `password`

### Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Administrator | maria.cruz@corp.ph | password |
| Inventory Staff | andrew.lim@corp.ph | password |
| Viewer | liza.reyes@corp.ph | password |

### Folder Structure (All files in root)
