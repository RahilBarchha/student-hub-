# End-to-End System Test Report - Practical 15

## Test Environment
- **Operating System**: Windows 11
- **Web Server**: Apache 2.4 (XAMPP)
- **PHP Version**: 8.2+
- **Database Engine**: MySQL 8.0 / MariaDB 10.4
- **Host Domain**: `http://localhost/practical%207/student%20hub%20portal/practical%2015/` and `http://studenthub.local`

## Test Execution Matrix

| Test ID | Module / Feature | Test Procedure | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| TC-01 | Database Auto-Initialization | Launch `index.php` with fresh database connection | Tables `users`, `students`, `events`, `registrations`, `audit_logs` created and seeded | Tables and sample data loaded without errors | PASS |
| TC-02 | Role Switching (Session Auth) | Click 'Admin Mode', 'Student Mode', and 'Faculty Mode' | Session updates user identity and role-based permissions immediately | Correct role assigned and logged in audit table | PASS |
| TC-03 | Practical Modules Navigator | Click each launch link for Practicals 2 to 14 | Each module loads with proper visual display without 404 broken links | All modules (P2 to P14) connect and render properly | PASS |
| TC-04 | CSRF Protection | Submit student creation form without valid CSRF token | Request rejected with security error alert | Rejected with 'Security Verification Failed' | PASS |
| TC-05 | Prepared Statements (SQLi) | Submit input containing `' OR '1'='1` in student name | Query treats input strictly as literal string without SQL injection | Safely stored as literal string in database | PASS |
| TC-06 | XSS Output Sanitization | Enter `<script>alert('XSS')</script>` in event venue | Output is sanitized via `htmlspecialchars` and safely displayed | Rendered safely as escaped text | PASS |
| TC-07 | Live AJAX Student Search | Type search query in student directory input | Matching students filtered instantaneously without full page reload | Dynamic instant client/AJAX update | PASS |
| TC-08 | AJAX Student Status Toggle | Click 'Toggle Status' button on student row | Status toggles Active <-> Inactive asynchronously and logs audit | Status badge toggles dynamically | PASS |
| TC-09 | Event Registration | Select student and open event, click 'Confirm Registration' | Record added to `registrations` and audit log updated | Registered with duplicate check verified | PASS |
| TC-10 | SQL Database Dump Download | Click 'Download SQL' button in header | Browser initiates download of `studenthub_p15.sql` file | `studenthub_p15.sql` downloaded successfully | PASS |
| TC-11 | Responsive Layout & Viewports | Test desktop, tablet (768px), and mobile (375px) viewports | Navigation, grid, and tables adapt cleanly without layout breaks | Mobile-first responsiveness confirmed | PASS |
| TC-12 | Apache Virtual Host | Access application via `http://studenthub.local` | Portal loads correctly via local DNS resolution | Resolves and serves application | PASS |

## Conclusion
All 12 critical integration tests passed successfully. System is stable, secure, fully integrated, and ready for deployment evaluation.
