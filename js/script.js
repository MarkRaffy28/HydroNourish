document.addEventListener("DOMContentLoaded", function() {
    // Sidebar navigation smooth scrolling
    const sidebarLinks = document.querySelectorAll('.sidebar-nav .nav-link, .sidebar-nav-mobile .nav-link');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const targetId = href.substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    const headerOffset = 80;
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    const offcanvas = document.getElementById('sidebarOffcanvas');
                    if (offcanvas && offcanvas.classList.contains('show')) {
                        bootstrap.Offcanvas.getInstance(offcanvas).hide();
                    }
 
                    document.querySelectorAll('.sidebar-nav .nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            }
        });
    });

    window.addEventListener('scroll', function() {
        const sections = ['dashboard', 'solar', 'irrigation', 'fertilizer', 'sensors', 'schedule'];
        let current = '';
        
        sections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                const sectionTop = section.offsetTop - 100;
                if (window.pageYOffset >= sectionTop) {
                    current = sectionId;
                }
            }
        });
        
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
    
    // DateTime display
    function updateDateTime() {
        const datetimeEl = document.getElementById('current-datetime');
        if (datetimeEl) {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit' 
            };
            datetimeEl.textContent = now.toLocaleDateString('en-US', options);
        }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Login form logic
    const form = document.getElementById("loginForm");
    
    if (form) {
        const email = document.getElementById("loginEmail");
        const password = document.getElementById("loginPassword");
        const togglePassword = document.getElementById("togglePassword");
        const icon = togglePassword ? togglePassword.querySelector("i") : null;

        const emailError = document.getElementById("emailError");
        const passwordError = document.getElementById("passwordError");

        // Toggle Password visibility
        if (togglePassword && icon) {
            togglePassword.addEventListener("click", function() {
                const isPassword = password.getAttribute("type") === "password";
                password.setAttribute("type", isPassword ? "text" : "password");
                icon.classList.toggle("fa-eye");
                icon.classList.toggle("fa-eye-slash");
            });
        }

        // Real-time email validation
        if (email) {
            email.addEventListener("input", function() {
                const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2}$/i;
                if (email.value.trim() === "") {
                    if (emailError) emailError.textContent = "";
                } else if (!emailPattern.test(email.value.trim())) {
                    if (emailError) emailError.textContent = "Invalid email format";
                } else {
                    if (emailError) emailError.textContent = "";
                }
            });
        }

        // Form validation
        form.addEventListener("submit", function(e) {
            e.preventDefault();

            if (emailError) emailError.textContent = "";
            if (passwordError) passwordError.textContent = "";

            let isValid = true;
            const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2}$/i;

            if (!email || email.value.trim() === "") {
                if (emailError) emailError.textContent = "Email is required";
                isValid = false;
            } else if (!emailPattern.test(email.value.trim())) {
                if (emailError) emailError.textContent = "Invalid email format";
                isValid = false;
            }

            if (!password || password.value.trim() === "") {
                if (passwordError) passwordError.textContent = "Password is required";
                isValid = false;
            } else if (password.value.length < 6) {
                if (passwordError) passwordError.textContent = "Minimum 6 characters required";
                isValid = false;
            }

            if (isValid) {
                Swal.fire({
                    icon: "success",
                    title: "Welcome Back!",
                    text: "Login Successful!",
                    confirmButtonColor: "#2d5a27",
                    confirmButtonText: '<i class="fa-solid fa-leaf"></i> Continue',
                    backdrop: `
                        rgba(45, 90, 39, 0.3)
                        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='50' font-size='50' opacity='0.2'%3E🍅%3C/text%3E%3C/svg%3E")
                    `
                }).then(() => {
                    const modall = bootstrap.Modal.getInstance(
                        document.getElementById("login")
                    );
                    if (modall) modall.hide();

                    form.reset();
                    if (password) password.setAttribute("type", "password");
                    if (icon) {
                        icon.classList.add("fa-eye-slash");
                        icon.classList.remove("fa-eye");
                    }

                    // Set logged in state and navigate to manage page
                    localStorage.setItem('isLoggedIn', 'true');
                    window.location.href = 'manage-irrigation-spray.html';
                });
            }
        });
    }

    // ========================================
    // IRRIGATION SYSTEM
    // ========================================
    let irrigationRunning = false;
    let irrigationInterval = null;
    let currentFlowRate = 15;

    window.toggleIrrigation = function() {
        irrigationRunning = !irrigationRunning;
        const startBtn = document.getElementById('start-irrigation');
        const stopBtn = document.getElementById('stop-irrigation');
        const valve = document.getElementById('water-valve');
        const valveStatus = document.getElementById('valve-status');
        const flowRateValue = document.getElementById('flow-rate-value');

        if (irrigationRunning) {
            startBtn.disabled = true;
            stopBtn.disabled = false;
            valve.classList.add('open');
            valveStatus.textContent = 'Open';
            valveStatus.className = 'text-danger fw-bold';

            irrigationInterval = setInterval(() => {
                currentFlowRate = Math.round((currentFlowRate + (Math.random() * 2 - 1)) * 10) / 10;
                currentFlowRate = Math.max(10, Math.min(20, currentFlowRate));
                flowRateValue.textContent = currentFlowRate;
            }, 1000);

            simulateMoistureIncrease();

            Swal.fire({
                icon: 'info',
                title: '<i class="fa-solid fa-water text-primary"></i> Irrigation Started',
                html: '<p class="mb-0">Water is now flowing to Zone A</p><p class="small text-muted">Flow Rate: ' + currentFlowRate + ' L/min</p>',
                confirmButtonColor: '#4fc3f7',
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            startBtn.disabled = false;
            stopBtn.disabled = true;
            valve.classList.remove('open');
            valveStatus.textContent = 'Closed';
            valveStatus.className = 'text-success fw-bold';

            if (irrigationInterval) {
                clearInterval(irrigationInterval);
            }

            Swal.fire({
                icon: 'success',
                title: '<i class="fa-solid fa-check text-success"></i> Irrigation Stopped',
                text: 'Water flow has been halted',
                confirmButtonColor: '#2d5a27'
            });
        }
    };

    window.toggleValve = function() {
        toggleIrrigation();
    };

    function simulateMoistureIncrease() {
        const zones = ['a', 'b', 'c'];
        const moistureLevels = { a: 68, b: 42, c: 75 };

        if (irrigationRunning) {
            zones.forEach(zone => {
                const currentLevel = moistureLevels[zone];
                if (currentLevel < 95) {
                    const newLevel = Math.min(95, currentLevel + Math.random() * 2);
                    moistureLevels[zone] = newLevel;

                    const moistureEl = document.getElementById('moisture-' + zone);
                    const levelEl = document.getElementById('moisture-level-' + zone);
                    if (moistureEl) moistureEl.textContent = Math.round(newLevel) + '%';
                    if (levelEl) levelEl.style.height = newLevel + '%';
                }
            });
        }
    }

    // ========================================
    // FERTILIZER SPRAYING SYSTEM
    // ========================================
    let sprayRunning = false;
    let sprayInterval = null;
    let chemicalLevel = 75;

    window.toggleSpray = function() {
        sprayRunning = !sprayRunning;
        const sprayBtn = document.getElementById('spray-btn');
        const warningSymbol = document.getElementById('warning-symbol');
        const gearCheck = document.getElementById('gear-check');

        if (sprayRunning) {
            if (gearCheck && !gearCheck.checked) {
                Swal.fire({
                    icon: 'error',
                    title: '<i class="fa-solid fa-exclamation-triangle text-warning"></i> Safety Alert',
                    text: 'Please ensure all protective gear is worn before fertilizing!',
                    confirmButtonColor: '#f57c00'
                });
                sprayRunning = false;
                return;
            }

            sprayBtn.classList.add('active');
            sprayBtn.innerHTML = '<i class="fa-solid fa-stop me-1"></i>Stop Fertilizing';

            sprayInterval = setInterval(() => {
                if (chemicalLevel > 0) {
                    chemicalLevel = Math.max(0, chemicalLevel - 0.5);
                    updateChemicalDisplay();
                } else {
                    stopSpray();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Chemical Tank Empty',
                        text: 'Please refill the fertilizer tank',
                        confirmButtonColor: '#795548'
                    });
                }
            }, 1000);

            Swal.fire({
                icon: 'warning',
                title: '<i class="fa-solid fa-spray-can text-warning"></i> Fertilizer Mode Active',
                html: '<p class="mb-0">Fertilization in progress</p><p class="small text-muted">Apply evenly across target zones</p>',
                confirmButtonColor: '#795548',
                timer: 5000,
                timerProgressBar: true
            });
        } else {
            stopSpray();
        }
    };

    function stopSpray() {
        sprayRunning = false;
        const sprayBtn = document.getElementById('spray-btn');
        if (sprayInterval) {
            clearInterval(sprayInterval);
        }
        if (sprayBtn) {
            sprayBtn.classList.remove('active');
            sprayBtn.innerHTML = '<i class="fa-solid fa-spray-can me-1"></i>Start Fertilizing';
        }
    }

    window.addChemical = function() {
        chemicalLevel = Math.min(100, chemicalLevel + 25);
        updateChemicalDisplay();
    };

    window.useChemical = function() {
        chemicalLevel = Math.max(0, chemicalLevel - 25);
        updateChemicalDisplay();
    };

    function updateChemicalDisplay() {
        const level = document.getElementById('chemical-level');
        const text = document.getElementById('chemical-text');
        const warning = document.getElementById('warning-symbol');

        if (level) {
            level.style.width = chemicalLevel + '%';
        }
        if (text) {
            text.textContent = Math.round(chemicalLevel) + '%';
        }
        if (level) {
            if (chemicalLevel <= 25) {
                level.classList.add('critical');
                level.classList.remove('warning');
            } else if (chemicalLevel <= 50) {
                level.classList.add('warning');
                level.classList.remove('critical');
            } else {
                level.classList.remove('warning', 'critical');
            }
        }
        if (warning) {
            warning.style.display = (chemicalLevel <= 50) ? 'block' : 'none';
        }
    }

    // ========================================
    // CALENDAR SYSTEM
    // ========================================
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    function generateCalendar() {
        const grid = document.getElementById('calendar-grid');
        const monthLabel = document.getElementById('current-month');

        if (!grid || !monthLabel) return;

        grid.innerHTML = '';
        monthLabel.textContent = monthNames[currentMonth] + ' ' + currentYear;

        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        days.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            grid.appendChild(header);
        });

        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            grid.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'calendar-day';
            dayEl.textContent = day;
          
            if (day % 7 === 5) { 
                dayEl.classList.add('watering');
                dayEl.title = 'Watering Day';
            }
            if (day % 7 === 1) { 
dayEl.classList.add('fertilization');
dayEl.title = 'Fertilization Day';
            }
            if (day % 10 === 3) {
                dayEl.style.borderColor = 'var(--tomato-red)';
                dayEl.title = 'Harvest Day';
            }

            dayEl.addEventListener('click', () => {
                showDayDetails(day);
            });

            grid.appendChild(dayEl);
        }
    }

    window.changeMonth = function(delta) {
        currentMonth += delta;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        } else if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar();
    };

    function showDayDetails(day) {
        const activities = [];

        if (day % 7 === 5) activities.push('💧 Morning Irrigation');
        if (day % 7 === 1) activities.push('🧪 Pest Control');
        if (day % 10 === 3) activities.push('🍅 Harvest Day');
        if (day % 14 === 0) activities.push('🌱 Fertilization');

        if (activities.length === 0) activities.push('✅ No scheduled activities');

        Swal.fire({
            title: day + ' ' + monthNames[currentMonth] + ' ' + currentYear,
            html: '<div class="text-start"><strong>Scheduled Activities:</strong><ul class="mb-0">' +
                activities.map(a => '<li>' + a + '</li>').join('') + '</ul></div>',
            confirmButtonColor: '#2d5a27'
        });
    }

    generateCalendar();

    // ========================================
    // COUNTER ANIMATIONS
    // ========================================
    const counters = document.querySelectorAll(".counter");
    counters.forEach(counter => {
        let target = parseFloat(counter.getAttribute('data-target'));
        let count = 0;
        let increment = target / 100;
        let updateCounter = () => {
            if (count < target) {
                count += increment;
                if (target % 1 !== 0) {
                    counter.innerText = count.toFixed(1);
                } else {
                    counter.innerText = Math.ceil(count);
                }
                setTimeout(updateCounter, 30);
            } else {
                if (target % 1 !== 0) {
                    counter.innerText = target.toFixed(1);
                } else {
                    counter.innerText = target;
                }
            }
        };
        updateCounter();
    });

    // ========================================
    // MOISTURE SENSOR SIMULATION
    // ========================================
    function simulateMoistureSensors() {
        const zones = ['a', 'b', 'c'];
        const baseLevels = { a: 68, b: 42, c: 75 };
        const variances = { a: 3, b: 5, c: 4 };

        zones.forEach(zone => {
            if (!irrigationRunning) {
                const moistureEl = document.getElementById('moisture-' + zone);
                if (moistureEl) {
                    const currentLevel = parseInt(moistureEl.textContent);
                    if (currentLevel > baseLevels[zone] - 10) {
                        const newLevel = Math.max(baseLevels[zone] - 10, currentLevel - Math.random() * variances[zone]);
                        moistureEl.textContent = Math.round(newLevel) + '%';
                        const levelEl = document.getElementById('moisture-level-' + zone);
                        if (levelEl) levelEl.style.height = newLevel + '%';
                    }
                }
            }
        });
    }

    // Update moisture every 3 seconds
    setInterval(simulateMoistureSensors, 3000);

    // ========================================
    // ALERT SYSTEM
    // ========================================
    function checkForAlerts() {
        const moistureAEl = document.getElementById('moisture-a');
        if (moistureAEl) {
            const moistureA = parseInt(moistureAEl.textContent);
            if (moistureA < 30) {
                showMoistureAlert(moistureA);
            }
        }
    }

    function showMoistureAlert(level) {
        Swal.fire({
            icon: 'warning',
            title: '<i class="fa-solid fa-exclamation-triangle text-warning"></i> Low Moisture Alert',
            html: '<p>Zone A moisture level is at ' + level + '%</p><p>Consider starting irrigation soon.</p>',
            confirmButtonColor: '#4fc3f7',
            showCancelButton: true,
            cancelButtonText: 'Ignore',
            confirmButtonText: '<i class="fa-solid fa-water"></i> Start Irrigation'
        }).then((result) => {
            if (result.isConfirmed) {
                const navbarNav = document.getElementById('navbarNav');
                if (navbarNav) {
                    navbarNav.scrollIntoView({ behavior: 'smooth' });
                    setTimeout(() => {
                        if (!irrigationRunning) {
                            toggleIrrigation();
                        }
                    }, 500);
                }
            }
        });
    }

    // Check for alerts every minute
    setInterval(checkForAlerts, 60000);
});
