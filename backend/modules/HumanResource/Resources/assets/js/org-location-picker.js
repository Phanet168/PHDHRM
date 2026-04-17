"use strict";

(function () {
    const defaultCenter = [11.5564, 104.9282]; // Phnom Penh
    const pickerRegistry = new WeakMap();

    function toNumber(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function setLatLngInputs(latInput, lngInput, lat, lng) {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
    }

    function initPicker(wrapper) {
        if (!window.L || pickerRegistry.has(wrapper)) {
            return;
        }

        const form = wrapper.closest("form");
        const latInput = form ? form.querySelector('input[name="latitude"]') : null;
        const lngInput = form ? form.querySelector('input[name="longitude"]') : null;
        const canvas = wrapper.querySelector(".org-map-canvas");

        if (!form || !latInput || !lngInput || !canvas) {
            return;
        }

        const latValue = toNumber(latInput.value);
        const lngValue = toNumber(lngInput.value);
        const center = latValue !== null && lngValue !== null ? [latValue, lngValue] : defaultCenter;
        const zoomLevel = latValue !== null && lngValue !== null ? 14 : 7;

        const map = L.map(canvas, { scrollWheelZoom: true }).setView(center, zoomLevel);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors",
        }).addTo(map);

        const marker = L.marker(center, { draggable: true }).addTo(map);

        marker.on("dragend", function (event) {
            const point = event.target.getLatLng();
            setLatLngInputs(latInput, lngInput, point.lat, point.lng);
        });

        map.on("click", function (event) {
            marker.setLatLng(event.latlng);
            setLatLngInputs(latInput, lngInput, event.latlng.lat, event.latlng.lng);
        });

        function syncFromInputs() {
            const lat = toNumber(latInput.value);
            const lng = toNumber(lngInput.value);
            if (lat === null || lng === null) {
                return;
            }
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], Math.max(map.getZoom(), 12));
        }

        latInput.addEventListener("change", syncFromInputs);
        lngInput.addEventListener("change", syncFromInputs);

        pickerRegistry.set(wrapper, map);
    }

    function initPickersInContainer(container) {
        const root = container || document;
        root.querySelectorAll("[data-map-picker]").forEach(initPicker);
    }

    document.addEventListener("shown.bs.modal", function (event) {
        initPickersInContainer(event.target);
        event.target.querySelectorAll("[data-map-picker]").forEach(function (wrapper) {
            const map = pickerRegistry.get(wrapper);
            if (map) {
                setTimeout(function () {
                    map.invalidateSize();
                }, 120);
            }
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
        initPickersInContainer(document);
    });
})();

