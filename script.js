// GLOBAL VARIABLES & CONSTANTS
let currentFormStep = 1;

// VEHICLE & PLAN PRICING SCHEMA
const PRICING_SCHEMA = {
    used: {
        deposit: 100,
        rates: {
            "Weekly": { weekly: 400, total: 400, weeks: 1 },
            "Monthly": { weekly: 350, total: 1400, weeks: 4 },
            "2 Months": { weekly: 325, total: 2600, weeks: 8 },
            "3 Months": { weekly: 300, total: 3600, weeks: 12 }
        },
        vehicles: ["2016 Hyundai Elantra", "2016 Hyundai Sonata", "2017 Hyundai Elantra"]
    },
    new: {
        deposit: 200,
        rates: {
            "Weekly": { weekly: 450, total: 450, weeks: 1 },
            "Monthly": { weekly: 400, total: 1600, weeks: 4 },
            "2 Months": { weekly: 375, total: 3000, weeks: 8 },
            "3 Months": { weekly: 350, total: 4200, weeks: 12 }
        },
        vehicles: ["2025 Hyundai Tucson", "2015 Kia Sorento", "2016 Hyundai Santa Fe"]
    }
};

// DOM CONTENT LOADING INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
    // Initialize Lucide icons
    lucide.createIcons();

    // 0. PREMIUM RIPPLE EFFECT — fires on every button click
    document.querySelectorAll(".btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            const ripple = document.createElement("span");
            ripple.classList.add("ripple");
            const rect = this.getBoundingClientRect();
            ripple.style.left = (e.clientX - rect.left - 3) + "px";
            ripple.style.top  = (e.clientY - rect.top  - 3) + "px";
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 700);
        });
    });

    // CARS CAROUSEL CONTROLLER
    (function() {
        const track    = document.getElementById("carsTrack");
        const prevBtn  = document.getElementById("carsPrev");
        const nextBtn  = document.getElementById("carsNext");
        const dotsEl   = document.getElementById("carsDots");
        if (!track || !prevBtn || !nextBtn) return;

        const cards      = Array.from(track.children);
        const total      = cards.length;          // 6 cards
        let   current    = 0;
        let   autoTimer  = null;

        function getVisibleCount() {
            return window.innerWidth <= 768 ? 1 : window.innerWidth <= 1024 ? 2 : 3;
        }

        function getMaxIndex() {
            return total - getVisibleCount();
        }

        // Build dots dynamically
        function buildDots() {
            dotsEl.innerHTML = "";
            const maxIdx = getMaxIndex();
            for (let i = 0; i <= maxIdx; i++) {
                const d = document.createElement("button");
                d.classList.add("carousel-dot");
                d.setAttribute("aria-label", "Slide " + (i + 1));
                d.addEventListener("click", () => goTo(i));
                dotsEl.appendChild(d);
            }
        }

        function goTo(idx) {
            const maxIdx = getMaxIndex();
            current = Math.max(0, Math.min(idx, maxIdx));
            const cardW = cards[0].getBoundingClientRect().width;
            const gap   = 28;
            track.style.transform = "translateX(-" + (current * (cardW + gap)) + "px)";
            
            const dots = Array.from(dotsEl.children);
            if (dots.length > 0) {
                dots.forEach((d, i) => d.classList.toggle("active", i === current));
            }
            
            prevBtn.style.opacity = current === 0      ? "0.35" : "1";
            nextBtn.style.opacity = current === maxIdx ? "0.35" : "1";
        }

        function next() { 
            const maxIdx = getMaxIndex();
            goTo(current < maxIdx ? current + 1 : 0); 
        }
        function prev() { 
            const maxIdx = getMaxIndex();
            goTo(current > 0 ? current - 1 : maxIdx); 
        }

        nextBtn.addEventListener("click", () => { next(); resetAuto(); });
        prevBtn.addEventListener("click", () => { prev(); resetAuto(); });

        function startAuto() { autoTimer = setInterval(next, 4000); }
        function resetAuto()  { clearInterval(autoTimer); startAuto(); }

        // Touch / swipe support
        let startX = 0;
        let isDragging = false;

        // Touch events (mobile)
        track.addEventListener("touchstart", e => { startX = e.touches[0].clientX; isDragging = true; }, { passive: true });
        track.addEventListener("touchend",   e => {
            if (!isDragging) return;
            const dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); resetAuto(); }
            isDragging = false;
        }, { passive: true });

        // Pointer events (desktop)
        track.addEventListener("pointerdown", e => { startX = e.clientX; });
        track.addEventListener("pointerup",   e => {
            const dx = e.clientX - startX;
            if (Math.abs(dx) > 50) { dx < 0 ? next() : prev(); resetAuto(); }
        });

        // Adjust tracking dimensions and dot counts on viewport resize
        window.addEventListener("resize", () => {
            buildDots();
            goTo(current);
        });

        buildDots();
        goTo(0);
        startAuto();
    })();

    // 1. FADE-OUT PRELOADER
    const preloader = document.getElementById("preloader");
    if (preloader) {
        setTimeout(() => {
            preloader.classList.add("fade-out");
            setTimeout(() => preloader.remove(), 800);
        }, 1200);
    }

    // 2. STICKY HEADER SCROLL LISTENER
    const header = document.getElementById("header");
    window.addEventListener("scroll", () => {
        if (window.scrollY > 50) {
            header.classList.add("scrolled");
        } else {
            header.classList.remove("scrolled");
        }
        
        // Highlight active nav links on scroll
        highlightNavOnScroll();
    });

    // 3. MOBILE MENU OVERLAY TOGGLE
    const menuToggle = document.getElementById("menuToggle");
    const mobileNav = document.getElementById("mobileNav");
    const mobileLinks = document.querySelectorAll(".mobile-link");

    if (menuToggle && mobileNav) {
        menuToggle.addEventListener("click", () => {
            menuToggle.classList.toggle("active");
            mobileNav.classList.toggle("active");
            document.body.classList.toggle("overflow-hidden");
        });

        mobileLinks.forEach(link => {
            link.addEventListener("click", () => {
                menuToggle.classList.remove("active");
                mobileNav.classList.remove("active");
                document.body.classList.remove("overflow-hidden");
            });
        });
    }

    // 4. INTERSECTION OBSERVER FOR SCROLL-REVEALS
    const revealElements = document.querySelectorAll(".scroll-reveal");
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("revealed");
                
                // Trigger counter animation if stat block is revealed
                if (entry.target.classList.contains("why-us-left")) {
                    animateStatsCounters();
                }
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    revealElements.forEach(el => revealObserver.observe(el));

    // 5. RENTAL PACKAGES INTERACTIVE SELECTION (Inside cards)
    const packageCards = document.querySelectorAll(".package-card");
    packageCards.forEach(card => {
        const rateItems = card.querySelectorAll(".rate-item");
        rateItems.forEach(item => {
            item.addEventListener("click", () => {
                // Remove active class from siblings
                rateItems.forEach(r => r.classList.remove("active"));
                item.classList.add("active");
                
                // Read plan values
                const tier = card.dataset.tier;
                const durationText = item.querySelector(".rate-duration").innerText;
                const durationKey = durationText.includes("Monthly") ? "Monthly" : 
                                    durationText.includes("2 Months") ? "2 Months" : "3 Months";
                
                // Update primary pricing displays inside header
                const planData = PRICING_SCHEMA[tier].rates[durationKey];
                card.querySelector(".price-val").innerText = `$${planData.weekly}`;
                card.querySelector(".price-unit").innerText = `/week (${durationKey} Plan)`;
            });
        });
    });

    // 6. TESTIMONIALS CAROUSEL
    (function() {
        const track    = document.getElementById("reviewsTrack");
        const prevBtn  = document.getElementById("reviewsPrev");
        const nextBtn  = document.getElementById("reviewsNext");
        const dotsEl   = document.getElementById("reviewsDots");
        if (!track || !prevBtn || !nextBtn) return;

        const cards      = Array.from(track.children);
        const total      = cards.length;          // 6 cards
        let   current    = 0;
        let   autoTimer  = null;

        function getVisibleCount() {
            return window.innerWidth <= 768 ? 1 : window.innerWidth <= 1024 ? 2 : 3;
        }

        function getMaxIndex() {
            return total - getVisibleCount();
        }

        // Build dots dynamically based on how many viewport index columns fit
        function buildDots() {
            dotsEl.innerHTML = "";
            const maxIdx = getMaxIndex();
            for (let i = 0; i <= maxIdx; i++) {
                const d = document.createElement("button");
                d.classList.add("carousel-dot");
                d.setAttribute("aria-label", "Slide " + (i + 1));
                d.addEventListener("click", () => goTo(i));
                dotsEl.appendChild(d);
            }
        }

        function goTo(idx) {
            const maxIdx = getMaxIndex();
            current = Math.max(0, Math.min(idx, maxIdx));
            const cardW = cards[0].getBoundingClientRect().width;
            const gap   = 30;
            track.style.transform = "translateX(-" + (current * (cardW + gap)) + "px)";
            
            const dots = Array.from(dotsEl.children);
            if (dots.length > 0) {
                dots.forEach((d, i) => d.classList.toggle("active", i === current));
            }
            
            prevBtn.style.opacity = current === 0      ? "0.35" : "1";
            nextBtn.style.opacity = current === maxIdx ? "0.35" : "1";
        }

        function next() {
            const maxIdx = getMaxIndex();
            goTo(current < maxIdx ? current + 1 : 0);
        }

        function prev() {
            const maxIdx = getMaxIndex();
            goTo(current > 0 ? current - 1 : maxIdx);
        }

        nextBtn.addEventListener("click", () => { next(); resetAuto(); });
        prevBtn.addEventListener("click", () => { prev(); resetAuto(); });

        function startAuto() { autoTimer = setInterval(next, 5000); }
        function resetAuto()  { clearInterval(autoTimer); startAuto(); }

        // Touch / swipe support
        let startX = 0;
        track.addEventListener("pointerdown", e => { startX = e.clientX; });
        track.addEventListener("pointerup",   e => {
            const dx = e.clientX - startX;
            if (Math.abs(dx) > 50) { dx < 0 ? next() : prev(); resetAuto(); }
        });

        // Adjust tracking dimensions and dot counts on viewport resize
        window.addEventListener("resize", () => {
            buildDots();
            goTo(current);
        });

        buildDots();
        goTo(0);
        startAuto();
    })();

    // 7. FAQ ACCORDION HANDLERS
    const faqTriggers = document.querySelectorAll(".faq-trigger");
    faqTriggers.forEach(trigger => {
        trigger.addEventListener("click", () => {
            const parent = trigger.parentElement;
            const panel = parent.querySelector(".faq-panel");
            
            if (parent.classList.contains("active")) {
                parent.classList.remove("active");
                panel.style.maxHeight = null;
            } else {
                // Close other panels
                document.querySelectorAll(".faq-item").forEach(item => {
                    item.classList.remove("active");
                    item.querySelector(".faq-panel").style.maxHeight = null;
                });
                
                parent.classList.add("active");
                panel.style.maxHeight = panel.scrollHeight + "px";
            }
        });
    });

    // 8. SETUP DOCUMENT UPLOAD DRAG-AND-DROP ZONES
    setupUploadZones();

    // 9. CALCULATOR INITIAL RUN
    updateDynamicRates();
});

// STICKY HEADER ACTIVE SCROLL INDICATOR
function highlightNavOnScroll() {
    const sections = document.querySelectorAll("section[id]");
    const scrollPos = window.scrollY + 120;
    
    sections.forEach(section => {
        const top = section.offsetTop;
        const height = section.offsetHeight;
        const id = section.getAttribute("id");
        const navLink = document.querySelector(`.nav-menu a[href="#${id}"]`);
        
        if (navLink) {
            if (scrollPos >= top && scrollPos < top + height) {
                document.querySelectorAll(".nav-link").forEach(l => l.classList.remove("active"));
                navLink.classList.add("active");
            }
        }
    });
}

// WHY CHOOSE US COUNTER ANIMATIONS
let statsAnimated = false;
function animateStatsCounters() {
    if (statsAnimated) return;
    statsAnimated = true;
    
    const counters = document.querySelectorAll(".stat-num");
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.val);
        let current = 0;
        const duration = 2000; // ms
        const stepTime = Math.max(Math.floor(duration / target), 15);
        
        const timer = setInterval(() => {
            current += 1;
            counter.innerText = current;
            if (current >= target) {
                counter.innerText = target;
                clearInterval(timer);
            }
        }, stepTime);
    });
}


// FLEET SELECTOR Action (Syncs with Wizard Form)
function selectVehicle(carName) {
    const vehicleDropdown = document.getElementById("vehicleDropdown");
    if (vehicleDropdown) {
        vehicleDropdown.value = carName;
        
        // Auto-select duration
        const rentalDuration = document.getElementById("rentalDuration");
        if (rentalDuration) rentalDuration.value = "Monthly";
        
        // Calculate Rates
        updateDynamicRates();
        
        // Navigate Wizard Form Step 2 (Rental Details)
        navigateStep(2);
        
        // Scroll smooth to application anchor
        const applySection = document.getElementById("apply");
        if (applySection) {
            applySection.scrollIntoView({ behavior: "smooth" });
        }
    }
}

// PACKAGE SELECTOR Action (Syncs with Wizard Form)
function selectRentalPlan(tier, durationLabel) {
    // Select first matching car of tier
    const vehiclesOfTier = PRICING_SCHEMA[tier].vehicles;
    const defaultCar = vehiclesOfTier[0];
    
    const vehicleDropdown = document.getElementById("vehicleDropdown");
    if (vehicleDropdown) vehicleDropdown.value = defaultCar;

    // Select duration
    const rentalDuration = document.getElementById("rentalDuration");
    const selectLabel = durationLabel.charAt(0).toUpperCase() + durationLabel.slice(1);
    if (rentalDuration) {
        if (selectLabel === "Monthly") rentalDuration.value = "Monthly";
        else if (selectLabel === "Weekly") rentalDuration.value = "Weekly";
        else if (selectLabel.includes("2")) rentalDuration.value = "2 Months";
        else if (selectLabel.includes("3")) rentalDuration.value = "3 Months";
    }

    updateDynamicRates();
    navigateStep(2);

    const applySection = document.getElementById("apply");
    if (applySection) {
        applySection.scrollIntoView({ behavior: "smooth" });
    }
}

// WIZARD FORM MULTI-STEP NAVIGATION
function navigateStep(targetStep) {
    // 1. Validation check if transitioning forward
    if (targetStep > currentFormStep) {
        const currentContainer = document.querySelector(`.form-step-content[data-step="${currentFormStep}"]`);
        const inputs = currentContainer.querySelectorAll("input[required], select[required]");
        let valid = true;
        
        inputs.forEach(input => {
            if (!input.value || (input.type === "checkbox" && !input.checked)) {
                valid = false;
                input.style.borderColor = "#FF3333";
                input.addEventListener("input", () => {
                    input.style.borderColor = "";
                }, { once: true });
            }
        });
        
        // Specifically check file uploads on step 3
        if (currentFormStep === 3) {
            const licenseVal = document.getElementById("uploadLicense").value;
            const addressVal = document.getElementById("uploadAddress").value;
            const insuranceVal = document.getElementById("uploadInsurance").value;
            const agreeCheck = document.getElementById("agreementCheckbox").checked;
            
            if (!licenseVal) { document.getElementById("zoneLicense").style.borderColor = "#FF3333"; valid = false; }
            if (!addressVal) { document.getElementById("zoneAddress").style.borderColor = "#FF3333"; valid = false; }
            if (!insuranceVal) { document.getElementById("zoneInsurance").style.borderColor = "#FF3333"; valid = false; }
            if (!agreeCheck) {
                const mark = document.querySelector(".checkbox-container .checkmark");
                if (mark) mark.style.borderColor = "#FF3333";
                valid = false;
            }
        }
        
        if (!valid) {
            // Flash some error visual alerts
            return;
        }
    }

    // 2. Shift active step panel
    document.querySelectorAll(".form-step-content").forEach(step => {
        step.classList.remove("active");
    });
    const targetContent = document.querySelector(`.form-step-content[data-step="${targetStep}"]`);
    if (targetContent) targetContent.classList.add("active");

    // 3. Update active nav button
    document.querySelectorAll(".step-nav-btn").forEach(btn => {
        btn.classList.remove("active");
        if (parseInt(btn.dataset.targetStep) <= targetStep) {
            btn.classList.add("active");
        }
    });

    currentFormStep = targetStep;
}

// INTERACTIVE DYNAMIC CONFIGURATOR RATES CALCULATOR
function updateDynamicRates() {
    const vehicleDropdown = document.getElementById("vehicleDropdown");
    const rentalDuration = document.getElementById("rentalDuration");
    
    if (!vehicleDropdown || !rentalDuration) return;

    const carName = vehicleDropdown.value;
    const durationVal = rentalDuration.value;

    if (!carName) return;

    // Detect Tier (Used vs New)
    let tier = "used";
    if (PRICING_SCHEMA.new.vehicles.includes(carName)) {
        tier = "new";
    }

    // Pull configurations
    const pricing = PRICING_SCHEMA[tier];
    const deposit = pricing.deposit;
    const rateData = pricing.rates[durationVal];

    if (!rateData) return;

    // Update estimated preview labels
    const calcPlan = document.getElementById("calcPlan");
    const calcWeekly = document.getElementById("calcWeekly");
    const calcDeposit = document.getElementById("calcDeposit");
    const calcTotal = document.getElementById("calcTotal");

    if (calcPlan) calcPlan.innerText = `${carName} (${durationVal} Plan)`;
    if (calcWeekly) calcWeekly.innerText = `$${rateData.weekly}/week`;
    if (calcDeposit) calcDeposit.innerText = `$${deposit} (Waived for credit card transactions)`;
    
    const finalTotal = rateData.total + deposit;
    if (calcTotal) calcTotal.innerText = `$${finalTotal} (Includes Refundable Deposit)`;
}

// SETUP FILE UPLOADER HANDLERS
function setupUploadZones() {
    const zones = document.querySelectorAll(".upload-zone");
    
    zones.forEach(zone => {
        const fileInput = zone.querySelector(".file-input");
        const preview = zone.querySelector(".upload-preview");
        const filenameSpan = zone.querySelector(".preview-filename");
        
        // Click zone triggers input select
        zone.addEventListener("click", (e) => {
            if (e.target.closest(".remove-file-btn")) return; // skip if removing
            fileInput.click();
        });

        // Drag/Drop Listeners
        zone.addEventListener("dragover", (e) => {
            e.preventDefault();
            zone.style.borderColor = "var(--color-gold)";
        });

        zone.addEventListener("dragleave", () => {
            zone.style.borderColor = "";
        });

        zone.addEventListener("drop", (e) => {
            e.preventDefault();
            zone.style.borderColor = "";
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelection(fileInput.files[0], zone, preview, filenameSpan);
            }
        });

        // Input selection listener
        fileInput.addEventListener("change", () => {
            if (fileInput.files.length) {
                handleFileSelection(fileInput.files[0], zone, preview, filenameSpan);
            }
        });
    });
}

// HANDLE FILE UPLOAD DISPLAY PREVIEW
function handleFileSelection(file, zone, preview, filenameSpan) {
    if (!file) return;
    
    // Display file name inside preview panel
    filenameSpan.innerText = file.name;
    preview.classList.remove("hidden");
    zone.style.borderColor = "var(--color-gold)";
}

// REMOVE UPLOADED FILE HANDLER
function removeUploadFile(zoneId) {
    const zone = document.getElementById(zoneId);
    if (!zone) return;
    
    const fileInput = zone.querySelector(".file-input");
    const preview = zone.querySelector(".upload-preview");
    
    fileInput.value = ""; // clear input
    preview.classList.add("hidden");
    zone.style.borderColor = "";
}

// AGREEMENT SCREEN MODALS
function openAgreementModal() {
    const modal = document.getElementById("agreementModal");
    if (modal) modal.classList.add("active");
    document.body.classList.add("overflow-hidden");
}

function closeAgreementModal() {
    const modal = document.getElementById("agreementModal");
    if (modal) modal.classList.remove("active");
    document.body.classList.remove("overflow-hidden");
}

// DIGITAL INITIAL CONFIRMATION ACCEPTANCE LINK
function acceptAgreementInForm() {
    const agreeCheckbox = document.getElementById("agreementCheckbox");
    if (agreeCheckbox) {
        agreeCheckbox.checked = true;
        // Clear border warnings
        const mark = document.querySelector(".checkbox-container .checkmark");
        if (mark) mark.style.borderColor = "";
    }
    closeAgreementModal();
}

// HANDLE APPLICATION WIZARD FORM SUBMIT
function handleFormSubmit(event) {
    event.preventDefault();
    
    // Complete validation on uploads and checkmarks
    const agreeCheck = document.getElementById("agreementCheckbox").checked;
    if (!agreeCheck) {
        openAgreementModal();
        return;
    }

    // Hide input forms and trigger SUCCESS visual dashboard feedback
    const applicationForm = document.getElementById("rentalApplicationForm");
    const successWrapper = document.getElementById("formSuccessState");
    const refCode = document.getElementById("refCode");

    if (applicationForm && successWrapper) {
        // Generate random reference code
        const chars = "ABCDEFGHJKLMNOPQRSTUVWXYZ0123456789";
        let code = "MYC-";
        for (let i = 0; i < 4; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        code += "-AZ";
        if (refCode) refCode.innerText = code;

        applicationForm.classList.add("hidden");
        successWrapper.classList.remove("hidden");
        
        // Scroll to form view top
        const formView = document.getElementById("apply");
        if (formView) {
            formView.scrollIntoView({ behavior: "smooth" });
        }
    }
}

// RESET FORM CONTROLS
function resetApplicationForm() {
    const applicationForm = document.getElementById("rentalApplicationForm");
    const successWrapper = document.getElementById("formSuccessState");
    
    if (applicationForm && successWrapper) {
        applicationForm.reset();
        
        // Remove uploaded files
        removeUploadFile("zoneLicense");
        removeUploadFile("zoneAddress");
        removeUploadFile("zoneInsurance");
        removeUploadFile("zoneSelfie");
        
        // Return step to index 1
        currentFormStep = 1;
        navigateStep(1);
        
        // Show form & Hide success wrapper
        successWrapper.classList.add("hidden");
        applicationForm.classList.remove("hidden");
        
        updateDynamicRates();
    }
}
// GOOGLE REVIEW DETAIL MODAL
function openReviewModal(name, date, initial, color, text) {
    const modal = document.getElementById("reviewDetailModal");
    const body  = document.getElementById("reviewModalBody");
    if (!modal || !body) return;

    body.innerHTML = `
        <div class="review-header" style="margin-bottom:24px;">
            <div class="review-user-block">
                <div class="review-avatar" style="background-color:${color};width:50px;height:50px;font-size:1.25rem;">${initial}</div>
                <div class="review-user-meta">
                    <h4 class="review-username" style="font-size:1.1rem;">${name}</h4>
                    <span class="review-date">${date}</span>
                </div>
            </div>
            <div class="google-badge">
                <svg class="google-icon" viewBox="0 0 24 24" width="18" height="18">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span class="google-text">Google Review</span>
            </div>
        </div>
        <div style="display:flex;gap:4px;margin-bottom:20px;">
            ${'<svg viewBox="0 0 24 24" width="20" height="20" fill="#FFC107"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>'.repeat(5)}
        </div>
        <p style="font-size:1rem;line-height:1.75;color:#111;margin:0;">${text}</p>
    `;

    modal.classList.add("active");
    document.body.classList.add("overflow-hidden");
}

function closeReviewModal() {
    const modal = document.getElementById("reviewDetailModal");
    if (modal) modal.classList.remove("active");
    document.body.classList.remove("overflow-hidden");
}
