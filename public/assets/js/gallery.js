(function () {
    var galleries = document.querySelectorAll('[data-gallery]');
    if (!galleries.length) { return; }
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var box = null, boxImg, boxCap, boxNow, boxItems = [], boxIndex = 0, boxOwner = null;

    var ICON_PREV = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var ICON_NEXT = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var ICON_CLOSE = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';

    function items(g) {
        return Array.prototype.map.call(g.querySelectorAll('.gx-slide img'), function (img) {
            return { url: img.getAttribute('data-full') || img.src, alt: img.alt, caption: img.getAttribute('data-caption') || '' };
        });
    }

    function buildBox() {
        box = document.createElement('dialog');
        box.className = 'lb';
        box.setAttribute('aria-label', 'Photo viewer');
        box.innerHTML =
            '<div class="lb-bar"><span class="lb-count"><span data-lb-now>1</span> / <span data-lb-all>1</span></span>' +
            '<button type="button" class="lb-btn lb-close" aria-label="Close photo viewer">' + ICON_CLOSE + '</button></div>' +
            '<div class="lb-stage"><button type="button" class="lb-btn lb-prev" aria-label="Previous photo">' + ICON_PREV + '</button>' +
            '<img class="lb-img" alt="">' +
            '<button type="button" class="lb-btn lb-next" aria-label="Next photo">' + ICON_NEXT + '</button></div>' +
            '<p class="lb-cap"></p>';
        document.body.appendChild(box);
        boxImg = box.querySelector('.lb-img');
        boxCap = box.querySelector('.lb-cap');
        boxNow = box.querySelector('[data-lb-now]');
        box.querySelector('.lb-close').addEventListener('click', function () { box.close(); });
        box.querySelector('.lb-prev').addEventListener('click', function () { showBox(boxIndex - 1); });
        box.querySelector('.lb-next').addEventListener('click', function () { showBox(boxIndex + 1); });
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target.classList.contains('lb-stage')) { box.close(); }
        });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { showBox(boxIndex - 1); e.preventDefault(); }
            if (e.key === 'ArrowRight') { showBox(boxIndex + 1); e.preventDefault(); }
        });
        box.addEventListener('close', function () {
            if (boxOwner) { boxOwner.scrollToIndex(boxIndex, true); }
            boxImg.removeAttribute('src');
        });
        var startX = null;
        boxImg.addEventListener('pointerdown', function (e) { startX = e.clientX; });
        boxImg.addEventListener('pointerup', function (e) {
            if (startX === null) { return; }
            var dx = e.clientX - startX;
            startX = null;
            if (Math.abs(dx) > 48) { showBox(boxIndex + (dx < 0 ? 1 : -1)); }
        });
        boxImg.addEventListener('pointercancel', function () { startX = null; });
    }

    function showBox(i) {
        var n = boxItems.length;
        boxIndex = ((i % n) + n) % n;
        var it = boxItems[boxIndex];
        boxImg.classList.add('is-loading');
        boxImg.onload = function () { boxImg.classList.remove('is-loading'); };
        boxImg.src = it.url;
        boxImg.alt = it.alt;
        boxCap.textContent = it.caption;
        boxCap.hidden = it.caption === '';
        boxNow.textContent = String(boxIndex + 1);
        var multi = n > 1;
        box.querySelector('.lb-prev').hidden = !multi;
        box.querySelector('.lb-next').hidden = !multi;
        box.querySelector('.lb-count').hidden = !multi;
        if (multi) {
            [1, -1].forEach(function (d) { new Image().src = boxItems[(boxIndex + d + n) % n].url; });
        }
    }

    function openBox(owner, i) {
        if (!box) { buildBox(); }
        if (typeof box.showModal !== 'function') { window.open(items(owner.el)[i].url, '_blank', 'noopener'); return; }
        boxOwner = owner;
        boxItems = items(owner.el);
        box.querySelector('[data-lb-all]').textContent = String(boxItems.length);
        showBox(i);
        box.showModal();
    }

    function setup(el) {
        var track = el.querySelector('.gx-track');
        var slides = track.querySelectorAll('.gx-slide');
        var thumbs = el.querySelectorAll('.gx-thumb');
        var strip = el.querySelector('.gx-thumbs');
        var now = el.querySelector('[data-gx-now]');
        var prev = el.querySelector('.gx-prev');
        var next = el.querySelector('.gx-next');
        var n = slides.length;
        var index = 0, ticking = false;

        function mark(i) {
            index = i;
            if (now) { now.textContent = String(i + 1); }
            thumbs.forEach(function (t, k) {
                t.classList.toggle('is-on', k === i);
                if (k === i) { t.setAttribute('aria-current', 'true'); } else { t.removeAttribute('aria-current'); }
            });
            if (prev) { prev.disabled = i === 0; }
            if (next) { next.disabled = i === n - 1; }
            if (strip && thumbs[i]) {
                var t = thumbs[i];
                strip.scrollTo({ left: t.offsetLeft - (strip.clientWidth - t.clientWidth) / 2, behavior: reduce ? 'auto' : 'smooth' });
            }
        }

        function scrollToIndex(i, instant) {
            i = Math.max(0, Math.min(n - 1, i));
            track.scrollTo({ left: slides[i].offsetLeft - track.offsetLeft, behavior: (instant || reduce) ? 'auto' : 'smooth' });
            mark(i);
        }

        track.addEventListener('scroll', function () {
            if (ticking) { return; }
            ticking = true;
            requestAnimationFrame(function () {
                ticking = false;
                var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
                if (i !== index && i >= 0 && i < n) { mark(i); }
            });
        }, { passive: true });

        if (prev) { prev.addEventListener('click', function () { scrollToIndex(index - 1); }); }
        if (next) { next.addEventListener('click', function () { scrollToIndex(index + 1); }); }
        thumbs.forEach(function (t, k) { t.addEventListener('click', function () { scrollToIndex(k); }); });
        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { scrollToIndex(index - 1); e.preventDefault(); }
            if (e.key === 'ArrowRight') { scrollToIndex(index + 1); e.preventDefault(); }
        });

        var owner = { el: el, scrollToIndex: scrollToIndex };
        el.querySelectorAll('.gx-open').forEach(function (b) {
            b.addEventListener('click', function () { openBox(owner, Number(b.getAttribute('data-index')) || 0); });
        });
        var zoom = el.querySelector('.gx-zoom');
        if (zoom) { zoom.addEventListener('click', function () { openBox(owner, index); }); }

        track.querySelectorAll('img').forEach(function (img) {
            img.addEventListener('error', function () { img.closest('.gx-slide').classList.add('is-broken'); });
        });
        mark(0);
    }

    galleries.forEach(setup);
})();
