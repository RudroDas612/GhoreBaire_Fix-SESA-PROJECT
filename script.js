// Shared navigation and home-page effects
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => navLinks.classList.toggle('open'));
    document.querySelectorAll('.nav-links a').forEach(link => link.addEventListener('click', () => navLinks.classList.remove('open')));
}
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => { if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 20); });
if (document.querySelector('.service-card, .technician-card, .testimonial-card, .hero-card')) {
    const cards = document.querySelectorAll('.service-card, .technician-card, .testimonial-card, .hero-card');
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.style.opacity = '1'; entry.target.style.transform = 'translateY(0)'; }
    }), { threshold: 0.1 });
    cards.forEach((card, i) => { card.style.opacity = '0'; card.style.transform = 'translateY(24px)'; card.style.transition = `opacity .6s ease ${i*.06}s, transform .6s ease ${i*.06}s`; observer.observe(card); });
}

// Login and signup connect to PHP using fetch()
if (location.pathname.includes('login.html')) {
    const tabs = document.querySelectorAll('.tabs button');
    const contents = { loginTab: document.getElementById('loginTab'), signupTab: document.getElementById('signupTab') };
    const footer = document.getElementById('footerMessage');
    function showTab(tabId) {
        tabs.forEach(button => button.classList.toggle('active', button.dataset.tab === tabId));
        Object.keys(contents).forEach(key => contents[key].classList.toggle('active', key === tabId));
        footer.innerHTML = tabId === 'loginTab' ? `Don't have an account? <a id="switchToSignup">Sign Up</a>` : `Already have an account? <a id="switchToLogin">Sign In</a>`;
    }
    tabs.forEach(button => button.addEventListener('click', () => showTab(button.dataset.tab)));
    const roleSelect = document.getElementById('signupRole');
    const techFields = document.getElementById('technicianSignupFields');
    const adminFields = document.getElementById('adminSignupFields');
    const adminCode = document.getElementById('adminCode');
    const techRequired = ['professionalTitle','serviceAreas','verificationDocument'];
    function syncTechnicianFields(){
        const isTech=roleSelect.value==='technician';
        const isAdmin=roleSelect.value==='admin';
        techFields.style.display=isTech?'block':'none';
        adminFields.style.display=isAdmin?'block':'none';
        adminCode.required=isAdmin;
        techRequired.forEach(id=>{const el=document.getElementById(id);el.required=isTech;});
    }
    roleSelect.addEventListener('change',syncTechnicianFields); syncTechnicianFields();
    footer.addEventListener('click', e => {
        if (e.target.id === 'switchToSignup') showTab('signupTab');
        if (e.target.id === 'switchToLogin') showTab('loginTab');
    });
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = document.getElementById('loginMessage');
        message.textContent = 'Please wait...';
        try {
            const response = await fetch('login.php', { method: 'POST', body: new FormData(this) });
            const data = await response.json();
            message.textContent = data.message;
            if (data.success) location.href = 'dashboard.php';
        } catch (error) { message.textContent = 'Could not connect. Run this website through XAMPP Apache.'; }
    });
    document.getElementById('signupForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = document.getElementById('signupMessage');
        message.textContent = 'Please wait...';
        try {
            const response = await fetch('signup.php', { method: 'POST', body: new FormData(this) });
            const data = await response.json();
            message.textContent = data.message;
            if (data.success) { this.reset(); showTab('loginTab'); document.getElementById('loginMessage').textContent = 'Account created. Please sign in.'; }
        } catch (error) { message.textContent = 'Could not connect. Run this website through XAMPP Apache.'; }
    });
}

// Three-step booking form. Technician cards are loaded from approved, available database profiles.
if (location.pathname.includes('book.html')) {
    let technicians = [];
    let selectedTech = null;
    const grid = document.getElementById('techGrid');
    const message = document.getElementById('bookingMessage');
    function renderTechnicians(list) {
        grid.innerHTML = '';
        selectedTech = null;
        if (!list.length) { grid.innerHTML = '<p class="form-message">No verified and available technicians match this category and location yet. Try another service area or come back later.</p>'; return; }
        list.forEach(tech => {
            const item = document.createElement('div'); item.className = 'tech-option';
            const title = document.createElement('h5'); title.textContent = tech.name;
            const specialty = document.createElement('small'); specialty.textContent = `${tech.specialty} · ${tech.experience_years} years experience`;
            const rating = document.createElement('div'); rating.className = 'rating'; rating.textContent = `★★★★★ ${Number(tech.rating).toFixed(1)} (${tech.reviews} reviews)`;
            const areas = document.createElement('small'); areas.textContent = `Service areas: ${tech.service_areas}`;
            item.append(title,rating,specialty,document.createElement('br'),areas);
            item.addEventListener('click', () => { document.querySelectorAll('.tech-option').forEach(el => el.classList.remove('selected')); item.classList.add('selected'); selectedTech = tech; });
            grid.appendChild(item);
        });
    }
    async function loadTechnicians() {
        grid.innerHTML = '<p>Finding verified technicians...</p>';
        const category = document.getElementById('category').value;
        const locationValue = document.getElementById('location').value.trim();
        try {
            const response = await fetch(`get_technicians.php?category=${encodeURIComponent(category)}&location=${encodeURIComponent(locationValue)}`);
            const data = await response.json();
            if (!data.success) { grid.innerHTML = `<p class="form-message">${data.message || 'Could not load technicians.'}</p>`; return; }
            technicians = data.technicians || []; renderTechnicians(technicians);
        } catch (e) { grid.innerHTML = '<p class="form-message">Could not load technicians. Please run the site through XAMPP.</p>'; }
    }
    function goToStep(step) {
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');
        document.querySelectorAll('.step-indicator').forEach((el, i) => el.classList.toggle('active', i + 1 <= step));
        if (step === 3) {
            document.getElementById('summaryCategory').textContent = document.getElementById('category').selectedOptions[0].text;
            document.getElementById('summarySubcategory').textContent = document.getElementById('subcategory').value || 'N/A';
            document.getElementById('summaryDescription').textContent = document.getElementById('description').value;
            document.getElementById('summaryLocation').textContent = document.getElementById('location').value;
            document.getElementById('summaryTechnician').textContent = selectedTech ? selectedTech.name : 'Not selected';
        }
    }
    document.getElementById('step1Next').addEventListener('click', async () => {
        const category = document.getElementById('category').value;
        const description = document.getElementById('description').value.trim();
        const place = document.getElementById('location').value.trim();
        if (!category || !description || !place) { alert('Please complete the required service details.'); return; }
        await loadTechnicians(); goToStep(2);
    });
    document.getElementById('step2Back').addEventListener('click', () => goToStep(1));
    document.getElementById('step2Next').addEventListener('click', () => { if (!selectedTech) { alert('Please select a verified and available technician.'); return; } goToStep(3); });
    document.getElementById('step3Back').addEventListener('click', () => goToStep(2));
    document.getElementById('confirmBooking').addEventListener('click', async () => {
        const date = document.getElementById('bookingDate').value;
        const time = document.getElementById('bookingTime').value;
        if (!date || !time) { alert('Please select a preferred date and time.'); return; }
        if (!selectedTech || !Number.isInteger(Number(selectedTech.id)) || Number(selectedTech.id) <= 0) {
            message.textContent = 'Your technician selection is missing. Please go back to Step 2 and select a technician again.';
            return;
        }
        const form = new FormData();
        form.append('category', document.getElementById('category').value);
        form.append('subcategory', document.getElementById('subcategory').value);
        form.append('description', document.getElementById('description').value);
        form.append('location', document.getElementById('location').value);
        form.append('technician_id', String(selectedTech.id));
        form.append('booking_date', date); form.append('booking_time', time);
        message.textContent = 'Sending your booking request...';
        try {
            const response = await fetch('create_booking.php', { method: 'POST', body: form });
            const data = await response.json();
            if (data.success) {
                document.querySelectorAll('.form-step, .booking-steps').forEach(el => el.style.display = 'none');
                document.getElementById('bookingSuccess').style.display = 'block';
                document.querySelector('#bookingSuccess h3').textContent = 'Booking Request Sent!';
                document.querySelector('#bookingSuccess p').textContent = data.message;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else { message.textContent = data.message; if (response.status === 401) setTimeout(() => location.href = 'login.html', 1200); }
        } catch (error) { message.textContent = 'Could not connect. Run this website through XAMPP Apache.'; }
    });
}
