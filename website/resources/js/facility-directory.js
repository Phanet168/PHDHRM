// The filter/sort/match logic below has no heavy dependencies and is
// registered eagerly so Alpine can resolve x-data="facilityDirectory(...)"
// at Alpine.start() time. Leaflet itself is only imported inside init(),
// so the map library stays out of the main bundle until this component
// actually mounts (i.e. only on the facilities page).
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

export function registerFacilityDirectory(Alpine) {
    Alpine.data('facilityDirectory', (facilities) => ({
        facilities,
        search: '',
        district: '',
        commune: '',
        type: '',
        view: 'map',
        sortBy: 'name_asc',
        selectedId: null,
        legend: {
            referral_hospital: true,
            health_center: true,
            health_post: true,
            operational_district_office: true,
        },
        map: null,
        L: null,
        markers: {},

        async init() {
            this.$watch('search', () => this.renderMarkers());
            this.$watch('district', () => this.renderMarkers());
            this.$watch('commune', () => this.renderMarkers());
            this.$watch('type', () => this.renderMarkers());

            const leaflet = await import('leaflet');
            this.L = leaflet.default ?? leaflet;
            this.mountMap();
            this.renderMarkers();
        },

        mountMap() {
            const container = document.getElementById('facility-map');
            if (!container || this.map) return;

            this.map = this.L.map(container, { scrollWheelZoom: true });
            this.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            this.map.setView([13.5259, 105.9683], 11);
            this.map.zoomControl.setPosition('bottomleft');
        },

        pinIcon(facility, selected = false) {
            const size = selected ? 36 : 30;
            return this.L.divIcon({
                className: '',
                html: `<div class="facility-marker-pin${selected ? ' is-selected' : ''}" style="background:${facility.color}"><span>${facility.icon ?? '●'}</span></div>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size],
                popupAnchor: [0, -size],
            });
        },

        matches(item) {
            const search = this.search.trim().toLowerCase();
            if (search && !String(item.name).toLowerCase().includes(search)) return false;
            if (this.district && item.district !== this.district) return false;
            if (this.commune && item.commune !== this.commune) return false;
            if (this.type && item.type !== this.type) return false;
            return true;
        },

        visibleCount() {
            return this.facilities.filter((f) => this.matches(f)).length;
        },

        renderMarkers() {
            if (!this.L || !this.map) return;

            Object.values(this.markers).forEach((marker) => marker.remove());
            this.markers = {};

            const visible = this.facilities.filter((f) => this.matches(f) && this.legend[f.type] && f.lat && f.lng);

            visible.forEach((f) => {
                const marker = this.L.marker([f.lat, f.lng], { icon: this.pinIcon(f, f.id === this.selectedId) });
                marker.bindPopup(this.popupHtml(f));
                marker.on('click', () => this.focusFacility(f.id, true));
                marker.addTo(this.map);
                this.markers[f.id] = marker;
            });

            if (visible.length) {
                const bounds = this.L.latLngBounds(visible.map((f) => [f.lat, f.lng]));
                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
            }
        },

        popupHtml(f) {
            return `<div class="facility-popup">
                <p class="facility-popup-title">${escapeHtml(f.name)}</p>
                <p class="facility-popup-meta">${escapeHtml(f.typeLabel)}${f.district ? ' · ' + escapeHtml(f.district) : ''}</p>
                <a class="facility-popup-link" href="${escapeHtml(f.url)}">មើលព័ត៌មាន →</a>
            </div>`;
        },

        focusFacility(id, fromMarker = false) {
            const previousId = this.selectedId;
            this.selectedId = id;

            if (previousId && previousId !== id && this.markers[previousId]) {
                const prevFacility = this.facilities.find((f) => f.id === previousId);
                if (prevFacility) this.markers[previousId].setIcon(this.pinIcon(prevFacility, false));
            }

            const facility = this.facilities.find((f) => f.id === id);
            const marker = this.markers[id];

            if (facility && marker) {
                marker.setIcon(this.pinIcon(facility, true));
            }

            if (facility && this.map && facility.lat && facility.lng) {
                this.map.flyTo([facility.lat, facility.lng], 15, { duration: 0.6 });
                marker?.openPopup();
            }

            if (!fromMarker) {
                const el = document.querySelector(`[data-facility-item="${id}"]`);
                el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        setView(view) {
            this.view = view;
            this.$nextTick(() => {
                if (view === 'map' && this.map) this.map.invalidateSize();
            });
        },

        resetFilters() {
            this.search = '';
            this.district = '';
            this.commune = '';
            this.type = '';
            Object.keys(this.legend).forEach((k) => (this.legend[k] = true));
            this.selectedId = null;
            this.renderMarkers();
        },

        applySort() {
            const container = document.getElementById('facility-list-scroll');
            if (!container) return;

            const items = Array.from(container.querySelectorAll('[data-facility-item]'));
            items.sort((a, b) => {
                if (this.sortBy === 'latest') {
                    return Number(b.dataset.publishedAt) - Number(a.dataset.publishedAt);
                }
                if (this.sortBy === 'name_desc') {
                    return b.dataset.name.localeCompare(a.dataset.name, 'km');
                }
                return a.dataset.name.localeCompare(b.dataset.name, 'km');
            });
            items.forEach((item) => container.appendChild(item));
        },
    }));
}
