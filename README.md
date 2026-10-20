# Schoolhouse — School Management System

A framework-free PHP and MySQL starter for nursery, primary, and secondary schools. A fresh installation has **no registered school**; the first visitor is guided through school registration and creation of the administrator account.

## Requirements

- PHP 8.1 or later with PDO MySQL and Fileinfo enabled
- MySQL 8.0 or later (or a compatible MariaDB release)
- Apache, such as the Apache server included with XAMPP

## Run locally with XAMPP

1. Copy this project into your Apache document root (for example, `C:\xampp\htdocs\school management`).
2. Start Apache and MySQL in the XAMPP control panel. **MySQL must be running** before you open the website; if MySQL is stopped, the app displays a setup message instead of a PHP stack trace.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and import `schema.sql`. The script creates the `school_management` database and its relational tables.
4. If your MySQL connection differs from the defaults, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the PHP/Apache environment before starting Apache. Defaults are `127.0.0.1`, `3306`, `school_management`, `root`, and an empty password. `DB_PORT` should match the MySQL port shown in XAMPP.
5. Visit `http://localhost/school%20management/`. With an empty database the school registration page appears. Register the school and first administrator to enter the dashboard.

For an existing installation, import `migrations/001_staff_profiles_and_password_resets.sql` once in phpMyAdmin before using staff profile pictures or password recovery. Back up the database first. The migration preserves announcements when a staff author is deleted. New installations receive these tables and columns through `schema.sql`.

The first setup inserts the Nursery, Primary, and Secondary sections. Use **Classes** to add class groups; create custom sections there as well.

Use the language selector in the page header or on the sign-in/setup screen to switch the interface between English, French, and Kiswahili. Your choice is remembered for the current browser session. School-entered content, such as student names and announcement messages, remains in the language it was entered in.

## Included workflows

- First-run school and administrator registration; password-hashed administrator sign-in and sign-out.
- Dashboard overview with student, staff, class, fee collection, and daily attendance metrics.
- Student and guardian contact registration, class assignment, staff directory, and attendance register.
- School sections, class groups, subjects, exam results, printable student report cards, assignments, and class timetables.
- To create an exam, assignment, or timetable entry, add at least one class under **Classes** and at least one subject under **Academics → Subjects** first. Then submit the exam or assignment form on the Academics page.
- Fee invoicing, payment recording, and outstanding balance tracking.
- School announcements, guardian contact selection, and a notification outbox for SMS, email, and mobile-app channels.
- Individual staff sign-in accounts for teachers, accountants, Directors of Studies, Heads of Studies, and behavior officers. Each account has a role-specific workspace access policy; school administrators create the account email and initial password.
- Administrators can add another administrator account from the staff directory. An administrator can delete their own account in **My profile** only after re-entering their password and only if another active administrator remains, so the school is not left without an administrator.
- Staff administrators can edit or delete staff records, update staff roles and sign-in details, disable access, and reset a staff password. Staff can edit their own name, email, phone, profile picture, and password from **My profile**. Profile photos accept JPG, PNG, or WebP up to 3 MB.
- Forgotten-password links are single-use and expire after one hour. To enable email recovery, configure `APP_URL` to the base URL of this installation (for example, `http://localhost/school%20management` locally), `MAIL_FROM` to a valid sender address, and configure PHP's `mail()` transport (for XAMPP, configure its sendmail settings). Until email is configured, staff should contact an administrator, who can set a temporary password in the staff editor.

Staff account access is restricted by role: teachers can use students, attendance, and academics; accountants can use fee management; DOS and Heads of Studies can manage students, classes, attendance, and academics; behavior officers can use students, attendance, and communications. Administrators retain access to every module and the staff directory.

## Notification delivery

Announcements are stored and eligible guardian destinations are placed in the database outbox. **The application does not send SMS, Gmail/email, or mobile-app notifications by itself.** Configure and integrate a trusted SMS gateway, SMTP/Gmail transport, or app push-notification provider before processing queued messages. Provider credentials should be stored in environment variables, not committed to source control. The attendance screen records attendance but does not automatically send absence alerts.

## Database

`schema.sql` defines the school, user/role, password-reset, section/class, student/guardian, teacher, subject, exam/result, assignment, timetable, attendance, fee, announcement, and notification-outbox tables, with foreign keys, uniqueness constraints, and school ownership columns. The starter currently provisions one school per database. Keep a backup of the database and use HTTPS before using real student information.
