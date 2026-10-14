export function initAboutPage() {
    const page = document.querySelector('[data-about-page]');
    if (!page) return;
    const links = [...page.querySelectorAll('[data-about-link]')];
    const dropdown = page.querySelector('[data-about-dropdown]');
    const summary = dropdown?.querySelector('summary');
    const current = page.querySelector('[data-about-current]');
    const targets = [...new Set(links.map(link => link.dataset.aboutLink))]
        .map(id => document.getElementById(id)).filter(Boolean);
    let active = links[0]?.dataset.aboutLink;
    let scheduled = false;
    const select = id => {
        const selected = links.find(link => link.dataset.aboutLink === id);
        if (!selected) return;
        active = id;
        for (const link of links) {
            const match = link.dataset.aboutLink === id;
            link.classList.toggle('is-active', match);
            if (match) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        }
        if (current) current.textContent = selected.textContent.trim();
    };
    const followHash = () => select(decodeURIComponent(location.hash.slice(1)));
    dropdown?.addEventListener('toggle', () => summary.setAttribute('aria-expanded', String(dropdown.open)));
    dropdown?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { dropdown.open = false; summary.focus(); }
    });
    for (const link of links) link.addEventListener('click', () => {
        select(link.dataset.aboutLink);
        if (dropdown) dropdown.open = false;
        const target = document.getElementById(link.dataset.aboutLink);
        if (target instanceof HTMLDetailsElement) target.open = true;
        if (target) { target.setAttribute('tabindex', '-1'); target.focus({ preventScroll: true }); }
    });
    window.addEventListener('hashchange', followHash);
    followHash();
    window.addEventListener('scroll', () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const offset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--public-sticky-offset')) || 128;
            const passed = targets.filter(target => target.getBoundingClientRect().top <= offset + 65);
            const latest = passed.sort((a, b) => b.getBoundingClientRect().top - a.getBoundingClientRect().top)[0];
            if (latest) {
                const sameRow = passed.find(target => target.id === active && Math.abs(target.getBoundingClientRect().top - latest.getBoundingClientRect().top) < 4);
                select((sameRow || latest).id);
            }
        });
    }, { passive: true });

    // Sidebar: stick only when it fits below the header, so it never needs its own scrollbar.
    const sidebar = page.querySelector('.about-sidebar');
    if (sidebar) {
        const fitSidebar = () => {
            const offset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--public-sticky-offset')) || 110;
            sidebar.classList.toggle('is-sticky', sidebar.offsetHeight + offset + 40 <= window.innerHeight);
        };
        fitSidebar();
        window.addEventListener('resize', fitSidebar);
        new ResizeObserver(fitSidebar).observe(sidebar);
    }

    // Leaders strip: native scroll-snap, arrows just scroll one card at a time.
    for (const carousel of page.querySelectorAll('[data-about-carousel]')) {
        const track = carousel.querySelector('[data-about-carousel-track]');
        const prev = carousel.querySelector('[data-about-carousel-prev]');
        const next = carousel.querySelector('[data-about-carousel-next]');
        const dots = carousel.querySelector('[data-about-carousel-dots]');
        let pageCount = 0;
        const update = () => {
            const max = track.scrollWidth - track.clientWidth;
            carousel.classList.toggle('is-scrollable', max > 4);
            if (prev) prev.disabled = track.scrollLeft <= 4;
            if (next) next.disabled = track.scrollLeft >= max - 4;
            if (!dots) return;
            const pages = max > 4 ? Math.ceil(track.scrollWidth / track.clientWidth - 0.05) : 0;
            if (pages !== pageCount) {
                pageCount = pages;
                dots.replaceChildren(...Array.from({ length: pages }, (_, index) => {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.setAttribute('aria-label', `ទំព័រទី ${index + 1}`);
                    dot.addEventListener('click', () => track.scrollTo({ left: index * track.clientWidth }));
                    return dot;
                }));
            }
            const current = max > 4 ? Math.round((track.scrollLeft / max) * (pages - 1)) : -1;
            [...dots.children].forEach((dot, index) => dot.setAttribute('aria-current', String(index === current)));
        };
        const step = direction => {
            const card = track.firstElementChild;
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            track.scrollBy({ left: direction * ((card?.getBoundingClientRect().width || track.clientWidth) + gap) });
        };
        prev?.addEventListener('click', () => step(-1));
        next?.addEventListener('click', () => step(1));
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    // Phones: organization groups start collapsed except the first, so the list stays short.
    const phone = window.matchMedia('(max-width: 767px)').matches;
    if (phone) {
        page.querySelectorAll('.about-unit-group').forEach((group, index) => { if (index > 0) group.open = false; });
    }

    // Toggle buttons that swap a "more"/"less" label and aria-expanded.
    const setExpanded = (button, expanded) => {
        button.setAttribute('aria-expanded', String(expanded));
        button.querySelector('[data-label-more]')?.toggleAttribute('hidden', expanded);
        button.querySelector('[data-label-less]')?.toggleAttribute('hidden', !expanded);
    };

    // History: clamp long text beside the image with a "អានបន្ថែម" button.
    for (const box of page.querySelectorAll('[data-about-clamp]')) {
        const prose = box.querySelector('.about-prose');
        const button = box.querySelector('[data-about-clamp-toggle]');
        box.setAttribute('data-clamped', '');
        if (prose.scrollHeight <= prose.clientHeight + 4) {
            box.removeAttribute('data-clamped');
            continue;
        }
        button.hidden = false;
        button.addEventListener('click', () => {
            const expanded = box.classList.toggle('is-expanded');
            setExpanded(button, expanded);
        });
    }

    // Organization: the chart is the summary on desktop; the full list opens on demand.
    const orgDetail = page.querySelector('[data-about-org-detail]');
    const orgToggle = page.querySelector('[data-about-org-toggle]');
    const showOrgDetail = show => {
        if (!orgDetail) return;
        orgDetail.hidden = !show;
        if (orgToggle) setExpanded(orgToggle, show);
    };
    if (orgDetail && orgToggle && !phone) {
        const hashTarget = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        showOrgDetail(Boolean(hashTarget && orgDetail.contains(hashTarget)));
        orgToggle.addEventListener('click', () => showOrgDetail(orgDetail.hidden));
    }
    for (const link of page.querySelectorAll('[data-about-org-open], [data-about-link]')) {
        link.addEventListener('click', () => {
            const target = document.getElementById(link.getAttribute('href').slice(1));
            if (!target || !orgDetail?.contains(target)) return;
            showOrgDetail(true);
            if (target instanceof HTMLDetailsElement) target.open = true;
        }, { capture: true });
    }

    // Reuse Leaflet/OSM, but only request tiles when the configured map approaches view.
    const mapElement = page.querySelector('[data-about-map]');
    if (mapElement) {
        const observer = new IntersectionObserver(async entries => {
            if (!entries.some(entry => entry.isIntersecting)) return;
            observer.disconnect();
            const { default: L } = await import('leaflet');
            const lat = Number(mapElement.dataset.lat);
            const lng = Number(mapElement.dataset.lng);
            const zoom = Math.max(1, Math.min(18, Number(mapElement.dataset.zoom) || 14));
            const map = L.map(mapElement, { scrollWheelZoom: false }).setView([lat, lng], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            const label = document.createElement('span');
            label.textContent = mapElement.dataset.markerTitle;
            L.circleMarker([lat, lng], { radius: 9, color: '#005cad', fillColor: '#005cad', fillOpacity: 0.8 }).addTo(map).bindPopup(label);
        }, { rootMargin: '200px' });
        observer.observe(mapElement);
    }
}
