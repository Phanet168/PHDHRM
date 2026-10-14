// Leaflet is only needed on the facility "location" admin tab, so it is
// fetched on demand instead of bundled into the site-wide admin entrypoint.
let loading = null;

export function initFacilityLocationPicker() {
    const container = document.getElementById('facility-location-picker');
    if (!container || container.dataset.mounted) return;

    loading ??= import('leaflet');
    loading.then((leaflet) => mount(leaflet.default ?? leaflet, container));
}

function mount(L, container) {
    if (container.dataset.mounted) return;
    container.dataset.mounted = '1';

    const latInput = document.querySelector(container.dataset.latInput);
    const lngInput = document.querySelector(container.dataset.lngInput);
    const defaultLat = parseFloat(container.dataset.defaultLat) || 13.5259;
    const defaultLng = parseFloat(container.dataset.defaultLng) || 105.9683;

    const existingLat = parseFloat(latInput?.value);
    const existingLng = parseFloat(lngInput?.value);
    const hasExisting = Number.isFinite(existingLat) && Number.isFinite(existingLng);

    const startLat = hasExisting ? existingLat : defaultLat;
    const startLng = hasExisting ? existingLng : defaultLng;

    const map = L.map(container).setView([startLat, startLng], hasExisting ? 15 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let marker = hasExisting ? L.marker([startLat, startLng], { draggable: true }).addTo(map) : null;

    const setPoint = (lat, lng) => {
        lat = Number(lat.toFixed(7));
        lng = Number(lng.toFixed(7));

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            bindDrag();
        } else {
            marker.setLatLng([lat, lng]);
        }

        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;
    };

    const bindDrag = () => {
        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            setPoint(pos.lat, pos.lng);
        });
    };

    if (marker) bindDrag();

    map.on('click', (event) => setPoint(event.latlng.lat, event.latlng.lng));

    const syncFromInputs = () => {
        const lat = parseFloat(latInput?.value);
        const lng = parseFloat(lngInput?.value);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            setPoint(lat, lng);
            map.setView([lat, lng], 15);
        }
    };

    latInput?.addEventListener('change', syncFromInputs);
    lngInput?.addEventListener('change', syncFromInputs);
}
