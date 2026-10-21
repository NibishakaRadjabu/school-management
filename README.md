# Schoolhouse — School Management System

A framework-free PHP and MySQL starter for nursery, primary, and secondary schools. A fresh installation has **no registered school**; the first visitor is guided through school registration and creation of the administrator account.

## Requirements

- PHP 8.1 or later with PDO MySQL and Fileinfo enabled
- MySQL 8.0 or later (or a compatible MariaDB release)
- Apache, such as the Apache server included with XAMPP
- Composer 2 for Gmail/SMTP email delivery

## Run locally with XAMPP

1. Copy this project into your Apache document root (for example, `C:\xampp\htdocs\school management`).
2. Start Apache and MySQL in the XAMPP control panel. **MySQL must be running** before you open the website; if MySQL is stopped, the app displays a setup message instead of a PHP stack trace.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and import `schema.sql`. The script creates the `school_management` database and its relational tables.
4. If your MySQL connection differs from the defaults, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the PHP/Apache environment before starting Apache. Defaults are `127.0.0.1`, `3306`, `school_management`, `root`, and an empty password. `DB_PORT` should match the MySQL port shown in XAMPP.
5. Visit `http://localhost/school%20management/`. With an empty database the school registration page appears. Register the school and first administrator to enter the dashboard.

For an existing installation, import `migrations/001_staff_profiles_and_password_resets.sql` if it has not already been applied, then import `migrations/002_student_staff_index_codes.sql`, `migrations/003_behavior_point_deductions.sql`, `migrations/004_behavior_deduction_signatures.sql`, and `migrations/005_parent_portal.sql`. Back up the database first. The migrations preserve announcements when a staff author is deleted, assign index codes to existing student and staff records, add behavior-point tracking and signatures, and add the parent-account link and email-verification table. New installations receive the required structure through `schema.sql`.

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
- Student registration creates a restricted sign-in account and automatically assigns an index code; staff profiles also receive an automatically generated index code. The code can identify an account during password recovery, but reset instructions are sent only to that account's registered email address.
- Parents can register using their child's admission number and the same parent email recorded in the student's guardian contact. The account must be activated through a one-time email verification link. Parents can then link other children using each child's admission number and see school announcements, marks, attendance, and fee balances for linked children only.
- Administrators and Behavior Officers can record student behavior-point deductions from the student register. Each student starts at 100 points; every deduction requires a specific misconduct description and is recorded in an auditable history. Scores cannot fall below zero.
- Each behavior deduction captures the student's and Behavior Officer's on-screen signatures with its recording date and time. Authorized staff can open and print each signed deduction from the recent behavior history.
- Administrators can provision sign-in accounts for existing students who were imported before this feature; their migration-generated index codes then work for email-verified recovery as well.
- Administrators can add another administrator account from the staff directory. An administrator can delete their own account in **My profile** only after re-entering their password and only if another active administrator remains, so the school is not left without an administrator.
- Staff administrators can edit or delete staff records, update staff roles and sign-in details, disable access, and reset a staff password. Staff can edit their own name, email, phone, profile picture, and password from **My profile**. Profile photos accept JPG, PNG, or WebP up to 3 MB.
- Forgotten-password links are single-use and expire after one hour. Users can enter their email or their student/staff index code to request recovery. A code alone never resets a password. To enable email recovery, configure `APP_URL` and the Gmail/SMTP environment variables described below. Until email is configured, staff should contact an administrator, who can set a temporary password in the staff editor.

Student accounts can access only their own profile and account settings; student access to academic records is not currently provided. Parent accounts can access only the parent portal and their own profile.

Staff account access is restricted by role: teachers can use students, attendance, and academics; accountants can use fee management; DOS and Heads of Studies can manage students, classes, attendance, and academics; behavior officers can use students, attendance, and communications. Administrators retain access to every module and the staff directory.

## Notification delivery

Announcements are stored and eligible guardian destinations are placed in the database outbox. Gmail/SMTP can deliver password-reset emails and queued announcement emails; SMS and mobile-app notifications still need their own provider integration. Provider credentials must be stored in environment variables, not committed to source control. The attendance screen records attendance but does not automatically send absence alerts.

### Gmail SMTP setup

1. Revoke any app password that has been shared or exposed, then create a replacement from the Google Account's **App passwords** page. Do not paste it into source code, chat, or this README.
2. From the project directory, install the mail dependency with `composer install`.
3. Set these environment variables for both Apache and the account that runs scheduled tasks:
   - `APP_URL`: public base URL of this installation, with no trailing slash.
   - `MAIL_HOST`: `smtp.gmail.com`.
   - `MAIL_PORT`: `587`.
   - `MAIL_ENCRYPTION`: `tls`.
   - `MAIL_USERNAME`: the full Gmail address.
   - `MAIL_PASSWORD`: the replacement Google app password, entered without spaces.
   - `MAIL_FROM`: the same Gmail address used for `MAIL_USERNAME`.
   - `MAIL_FROM_NAME`: optional sender display name; defaults to `Schoolhouse`.

   Parent registration and activation use this same configured mail transport. Make sure the school has a correct guardian email on the student's record before asking the parent to register; an admission number alone does not grant access.

   On XAMPP for Windows, use Windows system environment variables or add `SetEnv` entries to Apache's configuration outside the project directory, then restart Apache. For example:
   ```apache
   SetEnv APP_URL "http://localhost/school%20management"
   SetEnv MAIL_HOST "smtp.gmail.com"
   SetEnv MAIL_PORT "587"
   SetEnv MAIL_ENCRYPTION "tls"
   SetEnv MAIL_USERNAME "your-account@gmail.com"
   SetEnv MAIL_PASSWORD "your-new-app-password-without-spaces"
   SetEnv MAIL_FROM "your-account@gmail.com"
   SetEnv MAIL_FROM_NAME "Schoolhouse"
   ```
   Replace the example values locally and protect that configuration from other local users. Do not add secrets to a project `.env` file or commit them.
4. Request a password reset to verify that the SMTP connection works. For queued announcement emails, schedule this command with Windows Task Scheduler (for example, once per minute):
   `C:\xampp\php\php.exe "C:\xampp\htdocs\school management\bin\send_notification_emails.php" --limit=50`

The outbox worker sends queued email messages only; SMS and app rows remain queued for their respective integrations. Delivery errors are written to the PHP error log and mark that outbox item `failed`. Run only with valid SMTP configuration. Gmail may enforce sending limits or account restrictions.

## Database

`schema.sql` defines the school, user/role, password-reset, section/class, student/guardian, teacher, subject, exam/result, assignment, timetable, attendance, fee, announcement, and notification-outbox tables, with foreign keys, uniqueness constraints, and school ownership columns. Student and staff index codes are unique within a school. The starter currently provisions one school per database. Keep a backup of the database and use HTTPS before using real student information.
