(function() {
    var fades = document.querySelectorAll('.ab-fade');
    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    fades.forEach(function(el) { obs.observe(el); });
})();