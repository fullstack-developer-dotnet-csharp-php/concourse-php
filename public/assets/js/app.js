(function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    var menus = document.querySelectorAll('details.dd');
    menus.forEach(function (m) {
        m.addEventListener('toggle', function () {
            if (m.open) { menus.forEach(function (o) { if (o !== m) { o.open = false; } }); }
        });
    });
    document.addEventListener('click', function (e) {
        menus.forEach(function (m) { if (m.open && !m.contains(e.target)) { m.open = false; } });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        menus.forEach(function (m) { if (m.open) { m.open = false; m.querySelector('summary').focus(); } });
    });

    document.querySelectorAll('form[role="search"]').forEach(function (searchForm) {
        var input = searchForm.querySelector('input[name="q"]');
        if (!input) { return; }
        var list = document.createElement('ul');
        list.className = 'suggest';
        list.id = input.id + '-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        searchForm.appendChild(list);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');
        var timer = null;
        var controller = null;
        var active = -1;

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function highlight(i) {
            var rows = list.querySelectorAll('li');
            rows.forEach(function (r, n) { r.classList.toggle('is-active', n === i); r.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
            active = i;
            if (i >= 0) { input.setAttribute('aria-activedescendant', rows[i].id); } else { input.removeAttribute('aria-activedescendant'); }
        }

        function render(items, q) {
            list.innerHTML = '';
            if (!items.length) {
                var none = document.createElement('li');
                none.className = 'suggest-empty';
                none.textContent = 'No matches for "' + q + '"';
                list.appendChild(none);
            }
            items.forEach(function (it, n) {
                var li = document.createElement('li');
                li.id = list.id + '-' + n;
                li.setAttribute('role', 'option');
                var a = document.createElement('a');
                a.href = it.url;
                if (it.image) {
                    var img = document.createElement('img');
                    img.src = it.image;
                    img.alt = '';
                    img.width = 44;
                    img.height = 44;
                    a.appendChild(img);
                }
                var text = document.createElement('span');
                var name = document.createElement('strong');
                name.textContent = it.name;
                text.appendChild(name);
                if (it.place) {
                    var place = document.createElement('small');
                    place.textContent = it.place;
                    text.appendChild(place);
                }
                a.appendChild(text);
                if (it.price !== null && it.price !== undefined) {
                    var price = document.createElement('em');
                    price.textContent = 'from $' + Number(it.price).toFixed(2);
                    a.appendChild(price);
                }
                li.appendChild(a);
                list.appendChild(li);
            });
            var all = document.createElement('li');
            all.id = list.id + '-all';
            all.className = 'suggest-all';
            var link = document.createElement('a');
            link.href = '/attractions?q=' + encodeURIComponent(q);
            link.textContent = 'See all results for "' + q + '"';
            all.appendChild(link);
            list.appendChild(all);
            list.hidden = false;
            active = -1;
            input.setAttribute('aria-expanded', 'true');
        }

        function fetchSuggestions() {
            var q = input.value.trim();
            if (q.length < 3) { close(); return; }
            if (controller) { controller.abort(); }
            controller = new AbortController();
            fetch('/suggest?q=' + encodeURIComponent(q), { signal: controller.signal, headers: { Accept: 'application/json' } })
                .then(function (r) { if (!r.ok) { throw new Error('Suggestions failed with status ' + r.status); } return r.json(); })
                .then(function (d) { if (d.q === input.value.trim()) { render(d.items, d.q); } })
                .catch(function (err) { if (err.name !== 'AbortError') { console.error(err); close(); } });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(fetchSuggestions, 220);
        });
        input.addEventListener('keydown', function (e) {
            var rows = list.querySelectorAll('li:not(.suggest-empty)');
            if (e.key === 'Escape') { close(); return; }
            if (list.hidden || !rows.length) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % rows.length); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active <= 0 ? rows.length - 1 : active - 1); }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = rows[active].querySelector('a').href; }
        });
        input.addEventListener('focus', function () { if (input.value.trim().length >= 3 && list.innerHTML === '') { fetchSuggestions(); } });
        document.addEventListener('click', function (e) { if (!searchForm.contains(e.target)) { close(); } });
    });

    var settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        document.querySelectorAll('[data-test]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-test');
                var out = document.querySelector('[data-result="' + target + '"]');
                out.textContent = 'Testing...';
                out.className = 'test-result';
                var body = new URLSearchParams({ _csrf: document.getElementById('csrf-token').value });
                fetch('/admin/test/' + encodeURIComponent(target), { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { out.textContent = d.message; out.className = 'test-result ' + (d.ok ? 'is-ok' : 'is-bad'); })
                    .catch(function (err) { out.textContent = String(err); out.className = 'test-result is-bad'; });
            });
        });
    }

    var dataEl = document.getElementById('booking-data');
    var form = document.getElementById('booking');
    if (!dataEl || !form) { return; }
    var data = JSON.parse(dataEl.textContent);

    var offersEl = document.getElementById('offers');
    var slotsEl = document.getElementById('slots');
    var hint = document.getElementById('date-hint');
    var guestSection = document.getElementById('guest-section');
    var bar = document.getElementById('book-bar');
    var totalEl = document.getElementById('total');
    var errorEl = document.getElementById('form-error');
    var eventKeyEl = document.getElementById('eventKey');
    var visitDateEl = document.getElementById('visitDate');
    var bookingFields = document.getElementById('booking-fields');
    var travellersEl = document.getElementById('travellers');
    var entry = null;

    function el(tag, attrs, text) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (text !== undefined) { n.textContent = text; }
        return n;
    }

    function money(v) {
        if (v === null || v === undefined) { return ''; }
        var s = data.currency === 'USD' || !data.currency ? '$' : data.currency + ' ';
        return s + Number(v).toFixed(2);
    }

    function entryOffer(offer) {
        if (!entry) { return null; }
        if (offer.needsEvent === 'none') { return { price: offer.price, capacity: offer.maxQty }; }
        return entry.offers[offer.offerId] || null;
    }

    function selectDay(date) {
        document.querySelectorAll('.cal-on').forEach(function (b) { b.classList.toggle('is-selected', b.getAttribute('data-date') === date); });
        var rows = data.calendar[date] || [];
        visitDateEl.value = date;
        slotsEl.innerHTML = '';
        slotsEl.hidden = rows.length < 2;
        if (rows.length > 1) {
            rows.forEach(function (r, i) {
                var label = el('label');
                var radio = el('input', { type: 'radio', name: 'slot', value: String(i) });
                if (i === 0) { radio.checked = true; }
                radio.addEventListener('change', function () { setEntry(r); });
                label.appendChild(radio);
                label.appendChild(document.createTextNode(r.slot || 'Any time'));
                slotsEl.appendChild(label);
            });
        }
        setEntry(rows[0] || null);
    }

    function setEntry(r) {
        entry = r;
        eventKeyEl.value = r && r.eventKey ? r.eventKey : '';
        hint.textContent = '';
        renderOffers();
    }

    function renderOffers() {
        offersEl.innerHTML = '';
        data.offers.forEach(function (offer) {
            var info = entryOffer(offer);
            if (!offer.bookable || !info) { return; }
            var row = el('div', { class: 'offer-row' });
            var left = el('div');
            left.appendChild(el('strong', {}, offer.label));
            if (offer.age) { left.appendChild(el('span', { class: 'muted' }, ' (' + offer.age + ')')); }
            var p = el('div', { class: 'muted small' }, money(info.price) + (info.capacity !== null && info.capacity !== undefined && offer.needsEvent !== 'none' ? ' - ' + info.capacity + ' available' : ''));
            left.appendChild(p);
            var attrs = { type: 'number', name: 'qty[' + offer.offerId + ']', min: '0', value: '0', 'aria-label': offer.label + ' quantity', 'data-offer': offer.offerId, 'data-price': info.price === null ? '' : String(info.price) };
            if (info.capacity !== null && info.capacity !== undefined) { attrs.max = String(info.capacity); }
            var input = el('input', attrs);
            input.style.width = '84px';
            input.addEventListener('input', update);
            row.appendChild(left);
            row.appendChild(input);
            offersEl.appendChild(row);
        });
        if (!offersEl.children.length) { offersEl.appendChild(el('p', { class: 'muted' }, 'No tickets are available for this selection.')); }
        update();
    }

    function snapshot() {
        var values = {};
        form.querySelectorAll('#travellers [name], #booking-fields [name]').forEach(function (i) { values[i.name] = i.value; });
        return values;
    }

    var lookupBase = (function () {
        var m = location.pathname.match(/^\/attractions\/([0-9a-f]{24})\//);
        var p = document.getElementById('booking') ? document.getElementById('booking').getAttribute('action').match(/\/book\/([0-9a-f]{24})\/([0-9a-f]{24})/) : null;
        return p ? '/api/question-values/' + p[1] + '/' + p[2] + '/' : (m ? null : null);
    })();
    var listSeq = 0;

    function field(name, label, type, required, options, saved, q) {
        q = q || {};
        var wrap = el('label', {}, label + (required ? ' *' : ''));
        var input;
        if (q.valuesFrom === 'lookup' && lookupBase) {
            var listId = 'dl-' + (++listSeq);
            input = el('input', { name: name, type: 'text', list: listId, autocomplete: 'off' });
            var dl = el('datalist', { id: listId });
            (options || []).forEach(function (o) { dl.appendChild(el('option', { value: o.key }, o.label)); });
            wrap.appendChild(dl);
            var timer = null;
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var qs = new URLSearchParams({ q: input.value });
                    (q.valuesDependOn || []).forEach(function (k) {
                        var other = form.querySelector('[name$="[' + k + ']"]');
                        if (other && other.value) { qs.append('answer[' + k + ']', other.value); }
                    });
                    fetch(lookupBase + encodeURIComponent(q.key) + '?' + qs.toString())
                        .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                        .then(function (d) {
                            dl.innerHTML = '';
                            (d.items || []).forEach(function (i) { dl.appendChild(el('option', { value: i.key }, i.label + (i.detail ? ' - ' + i.detail : ''))); });
                        })
                        .catch(function () { dl.innerHTML = ''; });
                }, 250);
            });
        } else if (options && options.length) {
            input = el('select', { name: name });
            input.appendChild(el('option', { value: '' }, ''));
            options.forEach(function (o) { input.appendChild(el('option', { value: o.key }, o.label)); });
        } else {
            input = el('input', { name: name, type: type === 'date' ? 'date' : type === 'number' ? 'number' : type === 'email' ? 'email' : 'text' });
            if (q.maxLength) { input.setAttribute('maxlength', String(q.maxLength)); }
            if (type === 'number') { input.setAttribute('step', 'any'); }
        }
        if (required) { input.setAttribute('required', 'required'); }
        if (saved && saved[name] !== undefined) { input.value = saved[name]; }
        wrap.appendChild(input);
        if (q.units && q.units.length) {
            var unitName = name.replace(/^answers\[/, 'answers_unit[').replace(/^tanswers\[/, 'tanswers_unit[');
            var unit = el('select', { name: unitName, 'aria-label': label + ' unit' });
            q.units.forEach(function (u) { unit.appendChild(el('option', { value: u }, u)); });
            if (saved && saved[unitName] !== undefined) { unit.value = saved[unitName]; }
            wrap.appendChild(unit);
        }
        if (q.showWhen && q.showWhen.length) {
            wrap.setAttribute('data-show-when', JSON.stringify(q.showWhen));
        }
        return wrap;
    }

    function applyConditions() {
        form.querySelectorAll('[data-show-when]').forEach(function (wrap) {
            var rules = JSON.parse(wrap.getAttribute('data-show-when'));
            var scope = wrap.closest('.form-grid') || bookingFields;
            var show = rules.some(function (r) {
                var other = scope.querySelector('[name$="[' + r.key + ']"]');
                return other && (r.values || []).indexOf(other.value) !== -1;
            });
            wrap.hidden = !show;
            wrap.querySelectorAll('input, select').forEach(function (i) { i.disabled = !show; });
        });
    }
    form.addEventListener('input', applyConditions);
    form.addEventListener('change', applyConditions);

    var fieldLabels = { firstName: 'First name', lastName: 'Last name', dob: 'Date of birth', email: 'Email', phone: 'Phone' };
    var fieldTypes = { dob: 'date', email: 'email', phone: 'text', firstName: 'text', lastName: 'text' };

    function update() {
        var total = 0;
        var any = false;
        var lines = [];
        offersEl.querySelectorAll('input[data-offer]').forEach(function (i) {
            var raw = Number(i.value);
            var q = isFinite(raw) && raw > 0 ? Math.floor(raw) : 0;
            var max = i.getAttribute('max');
            if (max !== null && q > parseInt(max, 10)) { q = parseInt(max, 10); }
            if (i.value !== '' && String(q) !== i.value) { i.value = String(q); }
            if (q > 0) {
                any = true;
                lines.push({ offerId: i.getAttribute('data-offer'), qty: q });
                if (i.getAttribute('data-price') !== '') { total += q * parseFloat(i.getAttribute('data-price')); }
            }
        });
        guestSection.hidden = !any;
        bar.hidden = !any;
        totalEl.textContent = any ? money(total) : '-';

        var saved = snapshot();
        bookingFields.innerHTML = '';
        travellersEl.innerHTML = '';
        if (!any) { return; }
        var seen = {};
        lines.forEach(function (line) {
            var offer = data.offers.filter(function (o) { return o.offerId === line.offerId; })[0];
            (offer.travellerFields || []).forEach(function (f) {
                if (f.per !== 'booking' || f.field === 'firstName' || f.field === 'lastName' || f.field === 'email') { return; }
                var key = f.field === 'phone' ? 'guest_phone' : 'guest_dob';
                if (seen[key]) { return; }
                seen[key] = true;
                bookingFields.appendChild(field('answers[' + key + ']', fieldLabels[f.field], fieldTypes[f.field], f.required, null, saved));
            });
            (offer.questions || []).forEach(function (q) {
                if (q.per === 'traveller' || seen[q.key]) { return; }
                seen[q.key] = true;
                bookingFields.appendChild(field('answers[' + q.key + ']', q.label || q.key, q.type, q.required, q.options, saved, q));
            });
            var perTraveller = (offer.travellerFields || []).filter(function (f) { return f.per === 'traveller'; });
            var perQuestions = (offer.questions || []).filter(function (q) { return q.per === 'traveller'; });
            if (!perTraveller.length && !perQuestions.length) { return; }
            for (var n = 1; n <= line.qty; n++) {
                var box = el('div', { class: 'traveller' });
                box.appendChild(el('h3', {}, offer.label + ' - traveller ' + n));
                var grid = el('div', { class: 'form-grid' });
                perTraveller.forEach(function (f) {
                    grid.appendChild(field('traveller[' + offer.offerId + '][' + n + '][' + f.field + ']', fieldLabels[f.field] || f.field, fieldTypes[f.field] || 'text', f.required, null, saved));
                });
                perQuestions.forEach(function (q) {
                    grid.appendChild(field('tanswers[' + offer.offerId + '][' + n + '][' + q.key + ']', q.label || q.key, q.type, q.required, q.options, saved, q));
                });
                box.appendChild(grid);
                travellersEl.appendChild(box);
            }
        });
        applyConditions();
    }

    document.querySelectorAll('.cal-on').forEach(function (b) {
        b.addEventListener('click', function () { selectDay(b.getAttribute('data-date')); });
    });

    if (!data.needsEvent) { setEntry({ eventKey: null, offers: {} }); }

    form.addEventListener('submit', function (e) {
        var problem = '';
        if (data.needsEvent && !eventKeyEl.value) { problem = 'Choose a date first.'; }
        else if (bar.hidden) { problem = 'Select at least one ticket.'; }
        else if ((function () {
            var short = null;
            offersEl.querySelectorAll('input[data-offer]').forEach(function (i) {
                var q = parseInt(i.value, 10) || 0;
                var offer = data.offers.filter(function (o) { return o.offerId === i.getAttribute('data-offer'); })[0];
                if (q > 0 && q < offer.minQty) { short = offer.label + ' needs at least ' + offer.minQty + ' tickets.'; }
            });
            if (short) { problem = short; }
            return short;
        })()) { }
        else {
            var missing = Array.prototype.filter.call(form.querySelectorAll('[required]'), function (i) { return !i.value.trim(); });
            if (missing.length) { problem = 'Please fill in all required fields.'; missing[0].focus(); }
        }
        errorEl.hidden = problem === '';
        errorEl.textContent = problem;
        if (problem) { e.preventDefault(); }
    });
})();

(function () {
    var btn = document.querySelector('[data-near]');
    if (!btn || !('geolocation' in navigator)) { return; }
    btn.hidden = false;
    btn.addEventListener('click', function () {
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(function (pos) {
            location.href = '/attractions?near=' + pos.coords.latitude.toFixed(4) + ',' + pos.coords.longitude.toFixed(4);
        }, function () { btn.disabled = false; btn.textContent = 'Location is blocked in this browser'; }, { timeout: 10000 });
    });
})();
