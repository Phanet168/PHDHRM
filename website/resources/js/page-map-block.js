// Leaflet is only fetched on demand for public pages that actually contain a
// map block, mirroring the admin facility-location-picker's lazy-load.
export function initPageMapBlocks() {
    const containers = document.querySelectorAll('[data-page-map-block]');
    if (! containers.length) return;

    import('leaflet').then(({ default: L }) => {
        containers.forEach((el) => mount(L, el));
    });
}

function mount(L, container) {
    if (container.dataset.mounted) return;
    container.dataset.mounted = '1';

    const lat = parseFloat(container.dataset.lat) || 13.5259;
    const lng = parseFloat(container.dataset.lng) || 105.9683;
    const zoom = parseInt(container.dataset.zoom, 10) || 14;
    const title = container.dataset.markerTitle || '';

    const map = L.map(container, { scrollWheelZoom: false }).setView([lat, lng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const marker = L.marker([lat, lng]).addTo(map);
    if (title) marker.bindPopup(title).openPopup();
}
