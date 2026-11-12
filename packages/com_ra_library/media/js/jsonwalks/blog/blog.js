/*
 * Led walks blog display (PHP class Std/Blog)
 *  - click / Enter on a panel, or the 'More details' button, opens the standard walk popup
 *  - 'Show map' (or a map that loads when scrolled into view) for the full walks
 *  - flexible panel row: works out how many panels fit, adds previous/next buttons
 */
(function () {
    'use strict';

    function getWalk(id) {
        try {
            if (window.ra && ra.walk && typeof ra.walk.getEventID === 'function') {
                return ra.walk.getEventID(id);
            }
        } catch (e) {
        }
        return null;
    }

    function openWalk(event, id) {
        if (window.ra && ra.walk && typeof ra.walk.displayWalkID === 'function') {
            ra.walk.displayWalkID(event, id);
        }
    }

    function walkIdOf(element) {
        var holder = element.closest('[data-walk-id]');
        return holder === null ? null : holder.getAttribute('data-walk-id');
    }

    // ---- maps in the full walks ----
    function loadMap(article, attempt) {
        var holder = article.querySelector('.ra-fw-mapholder');
        if (holder === null || holder.getAttribute('data-loaded') === '1') {
            return;
        }
        var walk = getWalk(article.getAttribute('data-walk-id'));
        if (walk === null) {
            // walks are registered once the page has loaded; try again for a while
            if ((attempt || 0) < 20) {
                setTimeout(function () {
                    loadMap(article, (attempt || 0) + 1);
                }, 500);
            } else {
                holder.textContent = 'Sorry, unable to display the map.';
            }
            return;
        }
        holder.setAttribute('data-loaded', '1');
        walk.addMapSection(holder);
        if (!walk.isCancelled()) {
            walk.updatePostcodeInfo();
        }
        var button = article.querySelector('.ra-fw-showmap');
        if (button !== null) {
            button.style.display = 'none';
        }
    }

    function setupLazyMaps() {
        var places = document.querySelectorAll('.ra-fw-map[data-map-mode="always"]');
        if (places.length === 0) {
            return;
        }
        if (!('IntersectionObserver' in window)) {
            places.forEach(function (place) {
                loadMap(place.closest('[data-walk-id]'));
            });
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    loadMap(entry.target.closest('[data-walk-id]'));
                }
            });
        }, {rootMargin: '200px'});
        places.forEach(function (place) {
            observer.observe(place);
        });
    }

    // ---- flexible row of panels ----
    function setupPanels(panels) {
        var track = panels.querySelector('.ra-walkblog-track');
        if (track === null) {
            return;
        }
        var arrows = panels.getAttribute('data-arrows') === '1';
        var prev = null, next = null;

        function gapOf() {
            return parseFloat(getComputedStyle(track).columnGap) || 16;
        }

        function updateArrows() {
            var overflow = track.scrollWidth > track.clientWidth + 1;
            panels.classList.toggle('ra-no-overflow', !overflow);
            if (prev !== null) {
                prev.disabled = track.scrollLeft <= 1;
                next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
            }
        }

        function layout() {
            var min = parseFloat(getComputedStyle(panels).getPropertyValue('--ra-panel-min')) || 280;
            var style = getComputedStyle(track);
            var inner = track.clientWidth - (parseFloat(style.paddingLeft) || 0) - (parseFloat(style.paddingRight) || 0);
            var gap = gapOf();
            var n = Math.max(1, Math.floor((inner + gap) / (min + gap)));
            panels.style.setProperty('--ra-n', n);
            panels.classList.add('ra-enhanced');
            updateArrows();
        }

        function step(direction) {
            var first = track.querySelector('.ra-blog-panel');
            var width = first === null ? track.clientWidth : first.offsetWidth + gapOf();
            track.scrollBy({left: direction * width, behavior: 'smooth'});
        }

        function addButton(cls, label, text, direction) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'ra-walkblog-arrow ' + cls;
            button.setAttribute('aria-label', label);
            button.textContent = text;
            button.addEventListener('click', function () {
                step(direction);
            });
            panels.appendChild(button);
            return button;
        }

        if (arrows) {
            prev = addButton('prev', 'Previous walks', '‹', -1);
            next = addButton('next', 'Next walks', '›', 1);
        }
        track.addEventListener('scroll', updateArrows, {passive: true});
        if ('ResizeObserver' in window) {
            new ResizeObserver(layout).observe(track);
        } else {
            window.addEventListener('resize', layout);
        }
        layout();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        var showmap = target.closest('.ra-fw-showmap');
        if (showmap !== null) {
            loadMap(showmap.closest('[data-walk-id]'));
            return;
        }
        var more = target.closest('.ra-fw-showdetails');
        if (more !== null) {
            openWalk(event, walkIdOf(more));
            return;
        }
        var panel = target.closest('.ra-blog-panel');
        // links, and items with their own click action (such as the grade, which explains the grades), keep it
        var own = target.closest('a, [onclick]');
        if (panel !== null && (own === null || own === panel)) {
            openWalk(event, panel.getAttribute('data-walk-id'));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        var target = event.target;
        if (target instanceof Element && target.classList.contains('ra-blog-panel')) {
            event.preventDefault();
            openWalk(event, target.getAttribute('data-walk-id'));
        }
    });

    function init() {
        document.querySelectorAll('.ra-walkblog-panels.ra-mode-flexible').forEach(setupPanels);
        setupLazyMaps();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
