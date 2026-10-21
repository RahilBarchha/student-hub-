# StudentHub Portal - Practical 15 Final Integration & Deployment

## Problem Definition
Integrate all laboratory modules (Practical 2 to Practical 14) into a single deployable full-stack StudentHub portal. Verify navigation, responsiveness, authentication, database operations, security checks, AJAX functionality, and documentation. Deploy locally using XAMPP/Apache virtual host and prepare GitHub submission.

## Architecture and Connected Modules
The StudentHub portal serves as the unified umbrella application linking all individual practicals:
- **Practical 2**: HTML5 Structural Foundation & Core Forms (Login, Registration, Events, FAQs, Feedback, Profile)
- **Practical 3**: CSS3 Styling, Flexbox, CSS Grid & Responsive Layouts
- **Practical 4**: Responsive UI & Bootstrap Integration
- **Practical 5**: JavaScript Client-Side Form Validation with Regular Expressions
- **Practical 6**: Dynamic DOM Manipulation & Asynchronous JSON Data Rendering
- **Practical 7**: PHP Server-Side Handling & CSV/JSON Data Parsing
- **Practical 8**: PHP Form Handling, Input Validation & MySQL Connection Testing
- **Practical 9**: MySQL Database Integration & Full CRUD Operations
- **Practical 10**: State Management, Session Authentication, Cookies & Role Routing
- **Practical 11**: Security Controls, Input Sanitization, Prepared Statements, CSRF & XSS Prevention
- **Practical 12**: Dynamic AJAX Content, Live Data Search & Async File Uploads
- **Practical 13**: RESTful Web APIs, JSON Endpoints & Client Integration
- **Practical 14**: Administrator Dashboard, Role-Based Access Control (RBAC) & Audit Trails
- **Practical 15**: Unified Master Integration, Automated Database Migrations, VHost Deployment & Testing

## Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript (Fetch API, DOM Events)
- **Backend**: PHP 8 (Session Handling, MySQLi Prepared Statements, CSRF Guards)
- **Database**: MySQL / MariaDB (Database name: `studenthub`)
- **Web Server**: Apache HTTP Server 2.4 on XAMPP
- **Version Control**: Git & GitHub

## Database Setup
1. Ensure MySQL is running in XAMPP Control Panel.
2. The database `studenthub` and all tables (`users`, `students`, `events`, `registrations`, `audit_logs`) are automatically created upon first launching `index.php`.
3. Alternatively, import `studenthub_p15.sql` via phpMyAdmin at `http://localhost/phpmyadmin`.

## Local Virtual Host Configuration (studenthub.local)
### Step 1: Update Windows Hosts File
Edit `C:\Windows\System32\drivers\etc\hosts` using Administrator privileges:
```
127.0.0.1 studenthub.local
127.0.0.1 www.studenthub.local
```

### Step 2: Configure Apache Virtual Host
Append to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerAdmin barchharahil720@gmail.com
    DocumentRoot "C:/xampp/htdocs/practical 7/student hub portal/practical 15"
    ServerName studenthub.local
    ServerAlias www.studenthub.local
    <Directory "C:/xampp/htdocs/practical 7/student hub portal/practical 15">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/studenthub-error.log"
    CustomLog "logs/studenthub-access.log" common
</VirtualHost>
```

### Step 3: Enable Virtual Hosts in httpd.conf
Confirm `Include conf/extra/httpd-vhosts.conf` is active in `C:\xampp\apache\conf\httpd.conf`.

### Step 4: Restart Apache
Restart Apache from XAMPP Control Panel. Access `http://studenthub.local` in your browser.

## Default Credentials
- **Administrator Role**:
  - Email: `barchharahil720@gmail.com`
  - Password: `1@Rr2345`
- **Student Role**:
  - Email: `rohan@example.com`
  - Password: `Student@123`
- **Faculty Role**:
  - Email: `rajesh.mehta@example.com`
  - Password: `Student@123`

## GitHub Submission Steps
```bash
git init
git add .
git commit -m "Complete Practical 15 Final Integration and Deployment"
git branch -M main
git remote add origin https://github.com/rahilbarchha/studenthub-portal.git
git push -u origin main
```
