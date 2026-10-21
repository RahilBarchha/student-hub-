# Practical 15 Submission & Evaluation Checklist

## 1. Integration & Navigation Checklist
- [x] All practical modules (Practical 2 through Practical 14) connected via navigable links and live preview
- [x] Relative paths between modules verified and tested
- [x] Responsive navigation bar with mobile support
- [x] Tab-based interface for organized module inspection

## 2. Authentication & State Management Checklist
- [x] Role-Based Access Control implemented (Admin, Student, Faculty)
- [x] Dynamic role-switching for evaluation and testing
- [x] Session cookie security configured (HttpOnly, use_only_cookies)
- [x] Session destruction and logout workflow operational

## 3. Database Operations & CRUD Checklist
- [x] Database `studenthub` auto-created if missing
- [x] Tables created: `users`, `students`, `events`, `registrations`, `audit_logs`
- [x] Default administrator seeded: Rahil Barchha (barchharahil720@gmail.com)
- [x] Sample student and event datasets pre-populated
- [x] Insert, read, toggle status, and delete operations functional
- [x] Exportable and downloadable `studenthub_p15.sql` dump

## 4. Security Verification Checklist
- [x] CSRF synchronization token verification on state mutations
- [x] XSS protection via `htmlspecialchars` on all rendered variables
- [x] SQL injection defense using MySQLi parameterized prepared statements
- [x] Secure password storage using `password_hash` with BCRYPT
- [x] Audit trail recording user ID, action, details, IP, and timestamp

## 5. AJAX & Dynamic Content Checklist
- [x] Real-time live student search without page refresh
- [x] Asynchronous student status toggle via Fetch API
- [x] Live counters and statistics refresh
- [x] Graceful error and success alerts

## 6. Accessibility (WCAG 2.1) Checklist
- [x] Semantic HTML5 layout tags used throughout
- [x] Color contrast ratios exceed WCAG AA requirements
- [x] Explicit form input label bindings
- [x] Keyboard focus navigation verified

## 7. Local Deployment & Submission Checklist
- [x] Virtual host configuration documented (`studenthub.local`)
- [x] Windows hosts file entries provided
- [x] Apache `httpd-vhosts.conf` template created
- [x] `.htaccess` security headers configured
- [x] Complete README.md and TEST_REPORT.md documentation included
- [x] Git version control commands provided for GitHub submission
