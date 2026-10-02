// Apparition des blocs au défilement (sans effet si le navigateur préfère réduire les animations).
(function () {
    'use strict';
    var items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) {
        return; // vieux navigateur : tout reste visible, sans effet
    }
    document.documentElement.classList.add('js');
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.1 });
    items.forEach(function (el) { observer.observe(el); });
})();
