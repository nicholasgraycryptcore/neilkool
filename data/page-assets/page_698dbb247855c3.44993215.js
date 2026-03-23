(function() {
    var slides = document.querySelectorAll('.nk-slide');
    var dots = document.querySelectorAll('.nk-dot');
    var current = 0, total = slides.length, interval;

    function show(i) {
        slides.forEach(function(s) { s.classList.remove('active'); });
        dots.forEach(function(d) { d.classList.remove('active'); });
        current = (i + total) % total;
        slides[current].classList.add('active');
        if (dots[current]) dots[current].classList.add('active');
    }

    function start() { interval = setInterval(function() { show(current + 1); }, 5000); }

    window.nkSlide = function(dir) { clearInterval(interval); show(current + dir); start(); };
    window.nkGoTo = function(i) { clearInterval(interval); show(i); start(); };

    if (total > 1) start();

    // Scroll animations
    var fades = document.querySelectorAll('.nk-fade');
    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    fades.forEach(function(el) { obs.observe(el); });
})();

// Testimonial Slider
(function() {
    var track = document.getElementById('nkTestiTrack');
    var slides = document.querySelectorAll('.nk-testi-slide');
    var current = 0;
    var total = slides.length;
    var counter = document.getElementById('nkTestiCurrent');
    var autoInterval;

    function showSlide(i) {
        current = ((i % total) + total) % total;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        if (counter) counter.textContent = current + 1;
    }

    window.nkTestiNext = function() { clearInterval(autoInterval); showSlide(current + 1); startAuto(); };
    window.nkTestiPrev = function() { clearInterval(autoInterval); showSlide(current - 1); startAuto(); };

    function startAuto() { autoInterval = setInterval(function() { showSlide(current + 1); }, 6000); }
    startAuto();

    // Touch/swipe support
    var startX = 0, diff = 0;
    var slider = document.getElementById('nkTestiSlider');
    if (slider) {
        slider.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, {passive: true});
        slider.addEventListener('touchend', function(e) {
            diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) { clearInterval(autoInterval); diff > 0 ? showSlide(current+1) : showSlide(current-1); startAuto(); }
        }, {passive: true});
    }
})();
