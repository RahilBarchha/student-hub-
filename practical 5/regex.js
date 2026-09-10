
document.addEventListener("DOMContentLoaded", function () {
    // DOM Elements
    const form = document.getElementById("registrationForm");
    if (!form) return;

    const nameInput = document.getElementById("name");
    const emailInput = document.getElementById("email");
    const mobileInput = document.getElementById("mobile");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const courseSelect = document.getElementById("course");
    const yearRadios = document.querySelectorAll('input[name="year"]');
    const genderRadios = document.querySelectorAll('input[name="gender"]');
    const termsCheckbox = document.getElementById("terms");
    const captchaInput = document.getElementById("captchaInput");
    const captchaCanvas = document.getElementById("captchaCanvas");
    const refreshCaptchaBtn = document.getElementById("refreshCaptcha");

  
    const nameError = document.getElementById("nameError");
    const emailError = document.getElementById("emailError");
    const mobileError = document.getElementById("mobileError");
    const passwordError = document.getElementById("passwordError");
    const confirmPasswordError = document.getElementById("confirmPasswordError");
    const courseError = document.getElementById("courseError");
    const yearError = document.getElementById("yearError");
    const genderError = document.getElementById("genderError");
    const termsError = document.getElementById("termsError");
    const captchaError = document.getElementById("captchaError");
    const formMessage = document.getElementById("formMessage");

    
    const meterFill = document.getElementById("meterBarFill");
    const strengthLabel = document.getElementById("strengthLabel");
    const ruleLength = document.getElementById("ruleLength");
    const ruleUpper = document.getElementById("ruleUpper");
    const ruleLower = document.getElementById("ruleLower");
    const ruleNumber = document.getElementById("ruleNumber");
    const ruleSpecial = document.getElementById("ruleSpecial");

    
    const nameRegex = /^[A-Za-z]+(?:[ '][A-Za-z]+)*$/;

   
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    
    const mobileRegex = /^[6-9]\d{9}$/;

  
    const upperRegex = /[A-Z]/;
    const lowerRegex = /[a-z]/;
    const numberRegex = /[0-9]/;
    const specialRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;

   
    let generatedCaptcha = "";

    
    function generateCaptcha() {
        if (!captchaCanvas) return;
        const ctx = captchaCanvas.getContext("2d");
        const chars = "23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz";
        generatedCaptcha = "";
        const length = 6;

        for (let i = 0; i < length; i++) {
            generatedCaptcha += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        
        ctx.clearRect(0, 0, captchaCanvas.width, captchaCanvas.height);

       
        const gradient = ctx.createLinearGradient(0, 0, captchaCanvas.width, captchaCanvas.height);
        gradient.addColorStop(0, "#eef5ff");
        gradient.addColorStop(1, "#d5e5f8");
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, captchaCanvas.width, captchaCanvas.height);

       
        for (let i = 0; i < 5; i++) {
            ctx.beginPath();
            ctx.moveTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
            ctx.lineTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
            ctx.strokeStyle = "rgba(74, 144, 226, 0.4)";
            ctx.lineWidth = 1 + Math.random() * 1.5;
            ctx.stroke();
        }

       
        for (let i = 0; i < 35; i++) {
            ctx.beginPath();
            ctx.arc(
                Math.random() * captchaCanvas.width,
                Math.random() * captchaCanvas.height,
                1 + Math.random(),
                0,
                Math.PI * 2
            );
            ctx.fillStyle = "rgba(31, 78, 121, 0.35)";
            ctx.fill();
        }

        
        const charWidth = (captchaCanvas.width - 20) / length;
        for (let i = 0; i < length; i++) {
            ctx.save();
            const char = generatedCaptcha[i];
            const x = 12 + i * charWidth;
            const y = 28 + (Math.random() * 6 - 3);
            const angle = (Math.random() - 0.5) * 0.4; // -11 to +11 deg

            ctx.translate(x, y);
            ctx.rotate(angle);
            ctx.font = "bold 20px 'Segoe UI', Tahoma, sans-serif";
            
           
            const colors = ["#1f4e79", "#2c6fc2", "#0f3460", "#1e3a8a", "#1d4ed8"];
            ctx.fillStyle = colors[Math.floor(Math.random() * colors.length)];
            ctx.shadowColor = "rgba(0,0,0,0.15)";
            ctx.shadowBlur = 2;
            ctx.shadowOffsetX = 1;
            ctx.shadowOffsetY = 1;
            
            ctx.fillText(char, 0, 0);
            ctx.restore();
        }

       
        if (captchaInput) {
            captchaInput.value = "";
            setFieldState(captchaInput, captchaError, null);
        }
    }

  
    generateCaptcha();

   
    if (refreshCaptchaBtn) {
        refreshCaptchaBtn.addEventListener("click", function () {
            refreshCaptchaBtn.classList.add("spinning");
            generateCaptcha();
            setTimeout(() => refreshCaptchaBtn.classList.remove("spinning"), 500);
        });
    }

    if (captchaCanvas) {
        captchaCanvas.addEventListener("click", generateCaptcha);
    }

   
    function setFieldState(inputEl, errorEl, errorMessage) {
        if (!inputEl) return;
        if (errorMessage) {
            inputEl.classList.remove("is-valid");
            inputEl.classList.add("is-invalid");
            inputEl.setAttribute("aria-invalid", "true");
            if (errorEl) {
                errorEl.textContent = errorMessage;
            }
        } else if (errorMessage === null) {
            inputEl.classList.remove("is-valid", "is-invalid");
            inputEl.removeAttribute("aria-invalid");
            if (errorEl) {
                errorEl.textContent = "";
            }
        } else {
            inputEl.classList.remove("is-invalid");
            inputEl.classList.add("is-valid");
            inputEl.setAttribute("aria-invalid", "false");
            if (errorEl) {
                errorEl.textContent = "";
            }
        }
    }

   
    function validateName() {
        if (!nameInput) return true;
        const val = nameInput.value.trim();
        if (val === "") {
            setFieldState(nameInput, nameError, "Full name is required.");
            return false;
        }
        if (val.length < 2) {
            setFieldState(nameInput, nameError, "Name must be at least 2 characters long.");
            return false;
        }
        if (val.length > 50) {
            setFieldState(nameInput, nameError, "Name cannot exceed 50 characters.");
            return false;
        }
        if (!nameRegex.test(val)) {
            setFieldState(nameInput, nameError, "Enter a valid name (alphabets, spaces or hyphens only).");
            return false;
        }
        setFieldState(nameInput, nameError, "");
        return true;
    }

    
    function validateEmail() {
        if (!emailInput) return true;
        const val = emailInput.value.trim();
        if (val === "") {
            setFieldState(emailInput, emailError, "Email address is required.");
            return false;
        }
        if (!emailRegex.test(val)) {
            setFieldState(emailInput, emailError, "Enter a valid email address (e.g. student@college.edu).");
            return false;
        }
        setFieldState(emailInput, emailError, "");
        return true;
    }

   
    function validateMobile() {
        if (!mobileInput) return true;
        const val = mobileInput.value.trim();
        if (val === "") {
            setFieldState(mobileInput, mobileError, "Mobile number is required.");
            return false;
        }
        if (!mobileRegex.test(val)) {
            setFieldState(mobileInput, mobileError, "Enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.");
            return false;
        }
        setFieldState(mobileInput, mobileError, "");
        return true;
    }

    
    function validatePassword() {
        if (!passwordInput) return true;
        const val = passwordInput.value;

        // Update Checklist Rules
        const hasLength = val.length >= 8;
        const hasUpper = upperRegex.test(val);
        const hasLower = lowerRegex.test(val);
        const hasNumber = numberRegex.test(val);
        const hasSpecial = specialRegex.test(val);

        if (ruleLength) ruleLength.className = hasLength ? "met" : "unmet";
        if (ruleUpper) ruleUpper.className = hasUpper ? "met" : "unmet";
        if (ruleLower) ruleLower.className = hasLower ? "met" : "unmet";
        if (ruleNumber) ruleNumber.className = hasNumber ? "met" : "unmet";
        if (ruleSpecial) ruleSpecial.className = hasSpecial ? "met" : "unmet";

       
        let score = 0;
        if (hasLength) score++;
        if (hasUpper) score++;
        if (hasLower) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        
        if (meterFill && strengthLabel) {
            meterFill.className = "meter-bar-fill";
            strengthLabel.className = "strength-label";

            if (val.length === 0) {
                meterFill.style.width = "0%";
                strengthLabel.textContent = "None";
            } else if (score <= 2) {
                meterFill.classList.add("weak");
                strengthLabel.classList.add("weak");
                strengthLabel.textContent = "Weak";
            } else if (score === 3 || score === 4) {
                meterFill.classList.add("medium");
                strengthLabel.classList.add("medium");
                strengthLabel.textContent = "Medium";
            } else if (score === 5 && val.length < 12) {
                meterFill.classList.add("strong");
                strengthLabel.classList.add("strong");
                strengthLabel.textContent = "Strong";
            } else {
                meterFill.classList.add("very-strong");
                strengthLabel.classList.add("very-strong");
                strengthLabel.textContent = "Very Strong";
            }
        }

        if (val === "") {
            setFieldState(passwordInput, passwordError, "Password is required.");
            return false;
        }
        if (!hasLength) {
            setFieldState(passwordInput, passwordError, "Password must be at least 8 characters long.");
            return false;
        }
        if (!hasUpper) {
            setFieldState(passwordInput, passwordError, "Password must contain at least one uppercase letter (A-Z).");
            return false;
        }
        if (!hasLower) {
            setFieldState(passwordInput, passwordError, "Password must contain at least one lowercase letter (a-z).");
            return false;
        }
        if (!hasNumber) {
            setFieldState(passwordInput, passwordError, "Password must contain at least one numeric digit (0-9).");
            return false;
        }
        if (!hasSpecial) {
            setFieldState(passwordInput, passwordError, "Password must contain at least one special character (!@#$%^&*).");
            return false;
        }

        setFieldState(passwordInput, passwordError, "");
        return true;
    }

   
    function validateConfirmPassword() {
        if (!confirmPasswordInput || !passwordInput) return true;
        const val = confirmPasswordInput.value;
        if (val === "") {
            setFieldState(confirmPasswordInput, confirmPasswordError, "Please confirm your password.");
            return false;
        }
        if (val !== passwordInput.value) {
            setFieldState(confirmPasswordInput, confirmPasswordError, "Passwords do not match.");
            return false;
        }
        setFieldState(confirmPasswordInput, confirmPasswordError, "");
        return true;
    }

    
    function validateCourse() {
        if (!courseSelect) return true;
        if (courseSelect.value === "") {
            setFieldState(courseSelect, courseError, "Please select an academic course.");
            return false;
        }
        setFieldState(courseSelect, courseError, "");
        return true;
    }

    
    function validateYear() {
        const yearChecked = document.querySelector('input[name="year"]:checked');
        if (!yearChecked) {
            if (yearError) yearError.textContent = "Please select your academic year.";
            return false;
        }
        if (yearError) yearError.textContent = "";
        return true;
    }

   
    function validateGender() {
        const genderChecked = document.querySelector('input[name="gender"]:checked');
        if (!genderChecked) {
            if (genderError) genderError.textContent = "Please select your gender.";
            return false;
        }
        if (genderError) genderError.textContent = "";
        return true;
    }

    function validateTerms() {
        if (!termsCheckbox) return true;
        if (!termsCheckbox.checked) {
            if (termsError) termsError.textContent = "You must accept the terms and conditions to register.";
            return false;
        }
        if (termsError) termsError.textContent = "";
        return true;
    }

    
    function validateCaptcha() {
        if (!captchaInput) return true;
        const val = captchaInput.value.trim();
        if (val === "") {
            setFieldState(captchaInput, captchaError, "Please enter the CAPTCHA code shown.");
            return false;
        }
        if (val !== generatedCaptcha) {
            setFieldState(captchaInput, captchaError, "Incorrect CAPTCHA code. Please try again.");
            return false;
        }
        setFieldState(captchaInput, captchaError, "");
        return true;
    }

    
    nameInput?.addEventListener("input", validateName);
    nameInput?.addEventListener("blur", validateName);

    emailInput?.addEventListener("input", validateEmail);
    emailInput?.addEventListener("blur", validateEmail);

    mobileInput?.addEventListener("input", validateMobile);
    mobileInput?.addEventListener("blur", validateMobile);

    passwordInput?.addEventListener("input", function () {
        validatePassword();
        if (confirmPasswordInput && confirmPasswordInput.value !== "") {
            validateConfirmPassword();
        }
    });
    passwordInput?.addEventListener("blur", validatePassword);

    confirmPasswordInput?.addEventListener("input", validateConfirmPassword);
    confirmPasswordInput?.addEventListener("blur", validateConfirmPassword);

    courseSelect?.addEventListener("change", validateCourse);
    courseSelect?.addEventListener("blur", validateCourse);

    yearRadios.forEach(radio => radio.addEventListener("change", validateYear));
    genderRadios.forEach(radio => radio.addEventListener("change", validateGender));
    termsCheckbox?.addEventListener("change", validateTerms);

    captchaInput?.addEventListener("input", function () {
        if (captchaInput.value.length >= 6) {
            validateCaptcha();
        }
    });

  
    window.fillValidDemoData = function () {
        if (nameInput) nameInput.value = "Rahil Barchha";
        if (emailInput) emailInput.value = "rahil.student@hub.edu";
        if (mobileInput) mobileInput.value = "9876543210";
        if (passwordInput) passwordInput.value = "Student@2026";
        if (confirmPasswordInput) confirmPasswordInput.value = "Student@2026";
        if (courseSelect) courseSelect.value = "BTech_CSE";
        
        const yr = document.querySelector('input[name="year"][value="3"]');
        if (yr) yr.checked = true;

        const gen = document.querySelector('input[name="gender"][value="Male"]');
        if (gen) gen.checked = true;

        if (termsCheckbox) termsCheckbox.checked = true;
        if (captchaInput) captchaInput.value = generatedCaptcha;

        // Trigger validations
        validateName();
        validateEmail();
        validateMobile();
        validatePassword();
        validateConfirmPassword();
        validateCourse();
        validateYear();
        validateGender();
        validateTerms();
        validateCaptcha();

        if (formMessage) {
            formMessage.className = "form-message success";
            formMessage.textContent = "Valid test inputs loaded! Click 'Complete Registration' to submit.";
        }
    };

    window.fillInvalidDemoData = function () {
        if (nameInput) nameInput.value = "R@h1l 123";
        if (emailInput) emailInput.value = "invalid-email-format";
        if (mobileInput) mobileInput.value = "12345";
        if (passwordInput) passwordInput.value = "weak";
        if (confirmPasswordInput) confirmPasswordInput.value = "mismatch";
        if (courseSelect) courseSelect.value = "";
        
        yearRadios.forEach(r => r.checked = false);
        genderRadios.forEach(g => g.checked = false);
        if (termsCheckbox) termsCheckbox.checked = false;
        if (captchaInput) captchaInput.value = "WRONG";

        
        validateName();
        validateEmail();
        validateMobile();
        validatePassword();
        validateConfirmPassword();
        validateCourse();
        validateYear();
        validateGender();
        validateTerms();
        validateCaptcha();

        if (formMessage) {
            formMessage.className = "form-message error";
            formMessage.textContent = "Invalid test inputs loaded! Check the real-time regex error messages.";
        }
    };


    form.addEventListener("submit", function (event) {
        event.preventDefault();

        const isNameValid = validateName();
        const isEmailValid = validateEmail();
        const isMobileValid = validateMobile();
        const isPasswordValid = validatePassword();
        const isConfirmPasswordValid = validateConfirmPassword();
        const isCourseValid = validateCourse();
        const isYearValid = validateYear();
        const isGenderValid = validateGender();
        const isTermsValid = validateTerms();
        const isCaptchaValid = validateCaptcha();

        const allValid =
            isNameValid &&
            isEmailValid &&
            isMobileValid &&
            isPasswordValid &&
            isConfirmPasswordValid &&
            isCourseValid &&
            isYearValid &&
            isGenderValid &&
            isTermsValid &&
            isCaptchaValid;

        if (allValid) {
            if (formMessage) {
                formMessage.className = "form-message success";
                formMessage.textContent = "🎉 Registration successful! All regex validations and CAPTCHA passed.";
            }

           
            formMessage?.scrollIntoView({ behavior: "smooth", block: "nearest" });

            
            setTimeout(function () {
                form.reset();
                generateCaptcha();
                
                
                document.querySelectorAll(".form-control").forEach(ctrl => {
                    ctrl.classList.remove("is-valid", "is-invalid");
                    ctrl.removeAttribute("aria-invalid");
                });
                
                if (meterFill) {
                    meterFill.className = "meter-bar-fill";
                    meterFill.style.width = "0%";
                }
                if (strengthLabel) {
                    strengthLabel.className = "strength-label";
                    strengthLabel.textContent = "None";
                }
                [ruleLength, ruleUpper, ruleLower, ruleNumber, ruleSpecial].forEach(rule => {
                    if (rule) rule.className = "unmet";
                });
            }, 3000);
        } else {
            if (formMessage) {
                formMessage.className = "form-message error";
                formMessage.textContent = "⚠️ Please fix the highlighted validation errors before submitting.";
            }

            // Focus first invalid element
            const firstInvalid = form.querySelector(".is-invalid, :invalid");
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    });

    
    form.addEventListener("reset", function () {
        setTimeout(function () {
            generateCaptcha();
            document.querySelectorAll(".error-message").forEach(el => (el.textContent = ""));
            document.querySelectorAll(".form-control").forEach(ctrl => {
                ctrl.classList.remove("is-valid", "is-invalid");
                ctrl.removeAttribute("aria-invalid");
            });
            if (formMessage) {
                formMessage.className = "form-message";
                formMessage.style.display = "none";
            }
            if (meterFill) {
                meterFill.className = "meter-bar-fill";
                meterFill.style.width = "0%";
            }
            if (strengthLabel) {
                strengthLabel.className = "strength-label";
                strengthLabel.textContent = "None";
            }
            [ruleLength, ruleUpper, ruleLower, ruleNumber, ruleSpecial].forEach(rule => {
                if (rule) rule.className = "unmet";
            });
        }, 50);
    });
});