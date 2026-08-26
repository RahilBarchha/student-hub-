
document.addEventListener("DOMContentLoaded", () => {
    
    const themeToggle = document.querySelector("#themeToggle");
    const savedTheme = localStorage.getItem("theme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark-theme");
        if (themeToggle) themeToggle.checked = true;
    }

    themeToggle?.addEventListener("change", () => {
        const isDark = themeToggle.checked;
        document.body.classList.toggle("dark-theme", isDark);
        localStorage.setItem("theme", isDark ? "dark" : "light");
    });

   
    const menuButton = document.querySelector("#menuButton");
    const navMenu = document.querySelector("#navMenu");

    menuButton?.addEventListener("click", () => {
        const isOpen = navMenu.classList.toggle("show");
        menuButton.setAttribute("aria-expanded", String(isOpen));
        menuButton.setAttribute("aria-label", isOpen ? "Close navigation menu" : "Open navigation menu");
    });

    navMenu?.querySelectorAll("a").forEach(link => {
        link.addEventListener("click", () => {
            navMenu.classList.remove("show");
            menuButton?.setAttribute("aria-expanded", "false");
        });
    });


    document.querySelectorAll(".faq-question").forEach(question => {
        question.addEventListener("click", () => {
            const answer = question.nextElementSibling;
            const isOpen = answer.classList.toggle("show");
            answer.hidden = !isOpen;
            question.setAttribute("aria-expanded", String(isOpen));
        });
    });

    
    const modal = document.querySelector("#modal");
    const openModalButton = document.querySelector("#openModal");
    const closeModalButton = document.querySelector("#closeModal");

    const closeModal = () => {
        modal?.classList.remove("show");
        modal?.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    };

    openModalButton?.addEventListener("click", () => {
        modal?.classList.add("show");
        modal?.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
        closeModalButton?.focus();
    });

    closeModalButton?.addEventListener("click", closeModal);

    modal?.addEventListener("click", event => {
        if (event.target === modal) closeModal();
    });

    
    const slides = document.querySelectorAll(".slide");
    const previousButton = document.querySelector("#previousSlide");
    const nextButton = document.querySelector("#nextSlide");
    let currentSlide = 0;

    const showSlide = index => {
        if (!slides.length) return;
        currentSlide = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle("active", slideIndex === currentSlide);
        });
    };

    previousButton?.addEventListener("click", () => showSlide(currentSlide - 1));
    nextButton?.addEventListener("click", () => showSlide(currentSlide + 1));
    showSlide(0);

    // Auto-advance the slider when a page contains one.
    if (slides.length > 1) {
        setInterval(() => showSlide(currentSlide + 1), 5000);
    }


    const notification = document.querySelector("#notification");
    const dismissNotification = document.querySelector("#dismissNotification");

    dismissNotification?.addEventListener("click", () => {
        notification?.classList.add("hidden");
    });

   
    const setMessage = (id, text) => {
        const element = document.querySelector(id);
        if (element) element.textContent = text;
    };

    document.querySelector("#contactForm")?.addEventListener("submit", event => {
        event.preventDefault();
        setMessage("#contactMessage", "Message sent successfully! Thank you for contacting Student Hub.");
        event.target.reset();
    });

    document.querySelector("#feedbackForm")?.addEventListener("submit", event => {
        event.preventDefault();
        setMessage("#feedbackMessage", "Feedback submitted successfully. Thank you!");
        event.target.reset();
    });

    document.querySelector("#eventForm")?.addEventListener("submit", event => {
        event.preventDefault();
        setMessage("#eventMessage", "Event registration submitted successfully!");
        event.target.reset();
    });

    document.querySelector("#login")?.addEventListener("click", () => {
        const username = document.querySelector("#username")?.value.trim();
        const password = document.querySelector("#password")?.value.trim();

        if (!username || !password) {
            setMessage("#loginMessage", "Please enter both Student ID and Password.");
            return;
        }
        setMessage("#loginMessage", "Login details accepted. Welcome to Student Hub!");
    });

    document.querySelector("#register")?.addEventListener("click", () => {
        const username = document.querySelector("#username")?.value.trim();
        const password = document.querySelector("#password")?.value.trim();
        const email = document.querySelector("#email")?.value.trim();

        if (!username || !password || !email) {
            setMessage("#registerMessage", "Please fill in Student ID, Password and Email.");
            return;
        }
        setMessage("#registerMessage", "Registration completed successfully!");
    });

    
    document.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            closeModal();
            navMenu?.classList.remove("show");
            menuButton?.setAttribute("aria-expanded", "false");
        }
    });
});
