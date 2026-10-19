<?php
session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = "";
$val_username = "";
$val_fullname = "";
$val_email = "";
$val_mobile = "";
$val_course = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    $val_username = trim($_POST["username"] ?? "");
    $val_fullname = trim($_POST["fullname"] ?? "");
    $val_email = trim($_POST["email"] ?? "");
    $val_mobile = trim($_POST["mobile"] ?? "");
    $val_course = trim($_POST["course"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $terms = isset($_POST["terms"]);

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = "Security Error: CSRF token validation failed. Please refresh the page.";
    }

    if (empty($val_username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]{3,20}$/", $val_username)) {
        $errors[] = "Username must be 3-20 characters long and contain only letters, numbers, and underscores.";
    }

    if (empty($val_fullname)) {
        $errors[] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]{2,50}$/", $val_fullname)) {
        $errors[] = "Full Name must contain only letters and spaces (2-50 characters).";
    }

    if (empty($val_email)) {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($val_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    if (empty($val_mobile)) {
        $errors[] = "Mobile Number is required.";
    } elseif (!preg_match("/^[6-9]\d{9}$/", $val_mobile)) {
        $errors[] = "Mobile Number must be 10 digits starting with 6, 7, 8, or 9.";
    }

    if (empty($val_course)) {
        $errors[] = "Please select a course / branch.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/\d/", $password) || !preg_match("/[!@#$%^&*]/", $password)) {
        $errors[] = "Password must contain uppercase, lowercase, number, and special character (!@#$%^&*).";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Confirm Password does not match Password.";
    }

    if (!$terms) {
        $errors[] = "You must agree to the Terms & Conditions.";
    }

    if (empty($errors)) {
        $checkStmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ? OR username = ?");
        $checkStmt->bind_param("ss", $val_email, $val_username);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();

        while ($existingRow = $checkRes->fetch_assoc()) {
            if (strcasecmp($existingRow['email'], $val_email) === 0) {
                $errors[] = "Duplicate Check Failed: The email '" . htmlspecialchars($val_email) . "' is already registered.";
            }
            if (strcasecmp($existingRow['username'], $val_username) === 0) {
                $errors[] = "Duplicate Check Failed: The username '" . htmlspecialchars($val_username) . "' is already taken.";
            }
        }
        $checkStmt->close();
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insertStmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("ssssss", $val_username, $val_fullname, $val_email, $hashedPassword, $val_mobile, $val_course);

        if ($insertStmt->execute()) {
            $newUserId = $insertStmt->insert_id;
            $success = "Registration successful! Your account has been securely created with encrypted credentials.";

            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logAction = "USER_REGISTERED";
            $logDetails = "User '" . $val_username . "' (" . $val_email . ") registered. Password securely hashed with BCRYPT.";
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt->bind_param("isss", $newUserId, $logAction, $logDetails, $clientIp);
            $logStmt->execute();
            $logStmt->close();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $val_username = "";
            $val_fullname = "";
            $val_email = "";
            $val_mobile = "";
            $val_course = "";
        } else {
            $errors[] = "Database insertion error: " . $conn->error;
        }
        $insertStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .field-error-text {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
            display: block;
            min-height: 16px;
        }
        .admin-nav-link {
            background: #1f4e79 !important;
            color: #ffffff !important;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Learning • Innovation • Success</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Student Hub Portal</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="index.php" class="nav-btn active">Register</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
        <a href="../practical 2/About.html" class="nav-btn">About</a>
        <a href="../practical 10/login.php" class="nav-btn admin-nav-link">Admin</a>
    </nav>

    <main class="container">

        <div class="demo-helper-bar">
            <span style="font-size: 13px; font-weight: 600; color: var(--muted); align-self: center;">🧪 Quick Test:</span>
            <button type="button" class="demo-chip" onclick="fillValidCase()">✓ Valid Data</button>
            <button type="button" class="demo-chip" onclick="fillDuplicateEmailCase()">Duplicate Email</button>
            <button type="button" class="demo-chip" onclick="fillDuplicateUsernameCase()">Duplicate Username</button>
            <button type="button" class="demo-chip" onclick="fillInvalidCase()">✗ Invalid Data</button>
        </div>

        <h2 class="page-title">Student Registration</h2>

        <?php if (!empty($success)): ?>
            <div class="form-message success" style="display: block; margin-bottom: 20px;">
                <strong>✓ Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="form-message error" style="display: block; margin-bottom: 20px;">
                <strong>✗ Please correct the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="secureRegForm" action="index.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="username">
                    Username <span class="required-star">*</span>
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($val_username) ?>"
                    required
                >
                <span id="usernameError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="fullname">
                    Full Name <span class="required-star">*</span>
                </label>
                <input 
                    type="text" 
                    id="fullname" 
                    name="fullname" 
                    class="form-control" 
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($val_fullname) ?>"
                    required
                >
                <span id="fullnameError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="email">
                    Email Address <span class="required-star">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="e.g. student@college.edu"
                    value="<?= htmlspecialchars($val_email) ?>"
                    required
                >
                <span id="emailError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="mobile">
                    Mobile Number <span class="required-star">*</span>
                </label>
                <input 
                    type="tel" 
                    id="mobile" 
                    name="mobile" 
                    class="form-control" 
                    placeholder="10-digit mobile number"
                    maxlength="10"
                    value="<?= htmlspecialchars($val_mobile) ?>"
                    required
                >
                <span id="mobileError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="course">
                    Course / Branch <span class="required-star">*</span>
                </label>
                <select id="course" name="course" class="form-control" required>
                    <option value="">-- Select Course --</option>
                    <option value="BTech_CSE" <?= $val_course === 'BTech_CSE' ? 'selected' : '' ?>>B.Tech Computer Science & Engineering</option>
                    <option value="BTech_IT" <?= $val_course === 'BTech_IT' ? 'selected' : '' ?>>B.Tech Information Technology</option>
                    <option value="BCA" <?= $val_course === 'BCA' ? 'selected' : '' ?>>Bachelor of Computer Applications (BCA)</option>
                    <option value="MCA" <?= $val_course === 'MCA' ? 'selected' : '' ?>>Master of Computer Applications (MCA)</option>
                    <option value="BTech_AI" <?= $val_course === 'BTech_AI' ? 'selected' : '' ?>>B.Tech AI & Machine Learning</option>
                </select>
                <span id="courseError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="password">
                    Password <span class="required-star">*</span>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Create a strong password"
                    required
                >
                <div class="password-meter-container" style="margin-top: 8px;">
                    <div class="meter-bar-track">
                        <div id="meterBarFill" class="meter-bar-fill"></div>
                    </div>
                    <div class="strength-text" style="display: flex; justify-content: space-between; margin-top: 4px; font-size: 13px;">
                        <span>Password Strength:</span>
                        <span id="strengthLabel" class="strength-label" style="font-weight: bold;">None</span>
                    </div>
                    <ul class="password-checklist">
                        <li id="ruleLength" class="unmet">At least 8 characters</li>
                        <li id="ruleUpper" class="unmet">Uppercase letter (A-Z)</li>
                        <li id="ruleLower" class="unmet">Lowercase letter (a-z)</li>
                        <li id="ruleNumber" class="unmet">Numeric digit (0-9)</li>
                        <li id="ruleSpecial" class="unmet">Special character (!@#$%^&*)</li>
                    </ul>
                </div>
                <span id="passwordError" class="field-error-text"></span>
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Confirm Password <span class="required-star">*</span>
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-control" 
                    placeholder="Re-type your password"
                    required
                >
                <span id="confirmPasswordError" class="field-error-text"></span>
            </div>

            <div class="terms-group" style="margin-top: 15px;">
                <input type="checkbox" id="terms" name="terms" required checked>
                <label for="terms">
                    I agree to the <a href="../practical 2/About.html" class="terms-link">Terms & Conditions</a> and Portal Guidelines. <span class="required-star">*</span>
                </label>
            </div>
            <span id="termsError" class="field-error-text"></span>

            <div class="button-area" style="margin-top: 25px;">
                <button type="submit" class="btn" style="flex: 2; min-width: 200px;">Register</button>
                <button type="reset" class="btn" style="flex: 1; background: #64748b; min-width: 140px;" onclick="clearErrors()">Reset</button>
            </div>
        </form>

        <div class="button-area" style="margin-top: 30px;">
            <a href="../practical 10/login.php" class="btn" style="background: #1f4e79;">Admin Portal</a>
            <a href="../practical 2/login.html" class="btn" style="background: #475569;">Already have an account? Login</a>
        </div>

    </main>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | All Rights Reserved.</p>
            <p>Developed by Student Hub Team</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
    <script>
        const regForm = document.getElementById('secureRegForm');
        const usernameInput = document.getElementById('username');
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const mobileInput = document.getElementById('mobile');
        const courseInput = document.getElementById('course');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const termsInput = document.getElementById('terms');

        const meterBarFill = document.getElementById('meterBarFill');
        const strengthLabel = document.getElementById('strengthLabel');
        const ruleLength = document.getElementById('ruleLength');
        const ruleUpper = document.getElementById('ruleUpper');
        const ruleLower = document.getElementById('ruleLower');
        const ruleNumber = document.getElementById('ruleNumber');
        const ruleSpecial = document.getElementById('ruleSpecial');

        function updateChecklist(element, isMet) {
            if (isMet) {
                element.classList.remove('unmet');
                element.classList.add('met');
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
            }
        }

        function evaluatePassword(pwd) {
            const hasLen = pwd.length >= 8;
            const hasUp = /[A-Z]/.test(pwd);
            const hasLow = /[a-z]/.test(pwd);
            const hasNum = /\d/.test(pwd);
            const hasSpec = /[!@#$%^&*]/.test(pwd);

            updateChecklist(ruleLength, hasLen);
            updateChecklist(ruleUpper, hasUp);
            updateChecklist(ruleLower, hasLow);
            updateChecklist(ruleNumber, hasNum);
            updateChecklist(ruleSpecial, hasSpec);

            let score = 0;
            if (hasLen) score++;
            if (hasUp) score++;
            if (hasLow) score++;
            if (hasNum) score++;
            if (hasSpec) score++;

            if (pwd.length === 0) {
                meterBarFill.style.width = '0%';
                meterBarFill.style.backgroundColor = '#e2e8f0';
                strengthLabel.innerText = 'None';
                strengthLabel.style.color = 'var(--muted)';
            } else if (score <= 2) {
                meterBarFill.style.width = '25%';
                meterBarFill.style.backgroundColor = '#ef4444';
                strengthLabel.innerText = 'Weak';
                strengthLabel.style.color = '#ef4444';
            } else if (score <= 4) {
                meterBarFill.style.width = '65%';
                meterBarFill.style.backgroundColor = '#f59e0b';
                strengthLabel.innerText = 'Moderate';
                strengthLabel.style.color = '#f59e0b';
            } else {
                meterBarFill.style.width = '100%';
                meterBarFill.style.backgroundColor = '#10b981';
                strengthLabel.innerText = 'Strong';
                strengthLabel.style.color = '#10b981';
            }
        }

        passwordInput.addEventListener('input', () => {
            evaluatePassword(passwordInput.value);
            validateField(passwordInput);
            if (confirmPasswordInput.value) {
                validateField(confirmPasswordInput);
            }
        });

        confirmPasswordInput.addEventListener('input', () => {
            validateField(confirmPasswordInput);
        });

        usernameInput.addEventListener('input', () => validateField(usernameInput));
        fullnameInput.addEventListener('input', () => validateField(fullnameInput));
        emailInput.addEventListener('input', () => validateField(emailInput));
        mobileInput.addEventListener('input', () => validateField(mobileInput));
        courseInput.addEventListener('change', () => validateField(courseInput));
        termsInput.addEventListener('change', () => validateField(termsInput));

        function setFieldError(fieldId, errorText) {
            const errSpan = document.getElementById(fieldId + 'Error');
            if (errSpan) {
                errSpan.innerText = errorText;
            }
        }

        function validateField(element) {
            const id = element.id;
            const val = element.value.trim();

            if (id === 'username') {
                if (!val) {
                    setFieldError('username', 'Username is required.');
                    return false;
                } else if (!/^[a-zA-Z0-9_]{3,20}$/.test(val)) {
                    setFieldError('username', 'Must be 3-20 characters (letters, numbers, underscore).');
                    return false;
                }
                setFieldError('username', '');
                return true;
            }

            if (id === 'fullname') {
                if (!val) {
                    setFieldError('fullname', 'Full Name is required.');
                    return false;
                } else if (!/^[a-zA-Z\s]{2,50}$/.test(val)) {
                    setFieldError('fullname', 'Letters and spaces only (2-50 characters).');
                    return false;
                }
                setFieldError('fullname', '');
                return true;
            }

            if (id === 'email') {
                if (!val) {
                    setFieldError('email', 'Email Address is required.');
                    return false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                    setFieldError('email', 'Please enter a valid email address.');
                    return false;
                }
                setFieldError('email', '');
                return true;
            }

            if (id === 'mobile') {
                if (!val) {
                    setFieldError('mobile', 'Mobile number is required.');
                    return false;
                } else if (!/^[6-9]\d{9}$/.test(val)) {
                    setFieldError('mobile', 'Must be 10 digits starting with 6, 7, 8, or 9.');
                    return false;
                }
                setFieldError('mobile', '');
                return true;
            }

            if (id === 'course') {
                if (!element.value) {
                    setFieldError('course', 'Please select a course.');
                    return false;
                }
                setFieldError('course', '');
                return true;
            }

            if (id === 'password') {
                if (!element.value) {
                    setFieldError('password', 'Password is required.');
                    return false;
                } else if (element.value.length < 8) {
                    setFieldError('password', 'Password must be at least 8 characters long.');
                    return false;
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])/.test(element.value)) {
                    setFieldError('password', 'Include uppercase, lowercase, digit, and special char.');
                    return false;
                }
                setFieldError('password', '');
                return true;
            }

            if (id === 'confirm_password') {
                if (!element.value) {
                    setFieldError('confirmPassword', 'Please confirm your password.');
                    return false;
                } else if (element.value !== passwordInput.value) {
                    setFieldError('confirmPassword', 'Passwords do not match.');
                    return false;
                }
                setFieldError('confirmPassword', '');
                return true;
            }

            if (id === 'terms') {
                if (!element.checked) {
                    setFieldError('terms', 'You must agree to terms.');
                    return false;
                }
                setFieldError('terms', '');
                return true;
            }

            return true;
        }

        regForm.addEventListener('submit', (e) => {
            const v1 = validateField(usernameInput);
            const v2 = validateField(fullnameInput);
            const v3 = validateField(emailInput);
            const v4 = validateField(mobileInput);
            const v5 = validateField(courseInput);
            const v6 = validateField(passwordInput);
            const v7 = validateField(confirmPasswordInput);
            const v8 = validateField(termsInput);

            if (!v1 || !v2 || !v3 || !v4 || !v5 || !v6 || !v7 || !v8) {
                e.preventDefault();
            }
        });

        function clearErrors() {
            const errorSpans = document.querySelectorAll('.field-error-text');
            errorSpans.forEach(span => span.innerText = '');
            evaluatePassword('');
        }

        function fillValidCase() {
            clearErrors();
            const randSuffix = Math.floor(100 + Math.random() * 900);
            usernameInput.value = 'student_' + randSuffix;
            fullnameInput.value = 'Rohan Verma';
            emailInput.value = 'rohan' + randSuffix + '@example.com';
            mobileInput.value = '98765' + String(randSuffix) + '12';
            courseInput.value = 'BTech_IT';
            passwordInput.value = 'Student@' + randSuffix;
            confirmPasswordInput.value = 'Student@' + randSuffix;
            termsInput.checked = true;
            evaluatePassword(passwordInput.value);
        }

        function fillDuplicateEmailCase() {
            clearErrors();
            const randSuffix = Math.floor(100 + Math.random() * 900);
            usernameInput.value = 'fresh_user_' + randSuffix;
            fullnameInput.value = 'Same Email User';
            emailInput.value = 'rahil@example.com';
            mobileInput.value = '9811223344';
            courseInput.value = 'BTech_CSE';
            passwordInput.value = 'Duplicate@123';
            confirmPasswordInput.value = 'Duplicate@123';
            termsInput.checked = true;
            evaluatePassword(passwordInput.value);
        }

        function fillDuplicateUsernameCase() {
            clearErrors();
            const randSuffix = Math.floor(100 + Math.random() * 900);
            usernameInput.value = 'rahil_admin';
            fullnameInput.value = 'Different User Name';
            emailInput.value = 'unique_email_' + randSuffix + '@example.com';
            mobileInput.value = '9722334455';
            courseInput.value = 'BTech_AI';
            passwordInput.value = 'Duplicate@123';
            confirmPasswordInput.value = 'Duplicate@123';
            termsInput.checked = true;
            evaluatePassword(passwordInput.value);
        }

        function fillInvalidCase() {
            clearErrors();
            usernameInput.value = 'ab';
            fullnameInput.value = 'R';
            emailInput.value = 'invalid-email-address';
            mobileInput.value = '12345';
            courseInput.value = '';
            passwordInput.value = 'weak';
            confirmPasswordInput.value = 'different';
            termsInput.checked = false;
            evaluatePassword(passwordInput.value);
            validateField(usernameInput);
            validateField(fullnameInput);
            validateField(emailInput);
            validateField(mobileInput);
            validateField(courseInput);
            validateField(passwordInput);
            validateField(confirmPasswordInput);
            validateField(termsInput);
        }
    </script>
</body>
</html>
