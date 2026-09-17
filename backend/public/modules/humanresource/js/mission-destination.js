(function () {
    var gazetteerCache = null;
    var gazetteerPromise = null;

    function loadGazetteer(url) {
        if (Array.isArray(gazetteerCache)) {
            return Promise.resolve(gazetteerCache);
        }
        if (gazetteerPromise) {
            return gazetteerPromise;
        }
        gazetteerPromise = fetch(url)
            .then(function (res) { return res.ok ? res.json() : []; })
            .then(function (data) {
                gazetteerCache = Array.isArray(data) ? data : [];
                return gazetteerCache;
            })
            .catch(function () {
                gazetteerCache = [];
                return gazetteerCache;
            });
        return gazetteerPromise;
    }

    function fillSelect(select, items, placeholder) {
        select.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        select.appendChild(opt);
        (items || []).forEach(function (item) {
            var o = document.createElement('option');
            o.value = item.code;
            o.textContent = item.khmer || item.latin || item.code;
            select.appendChild(o);
        });
    }

    function findByCode(list, code) {
        return (list || []).filter(function (item) { return item.code === code; })[0] || null;
    }

    function composeDestinationText(root) {
        var venue = root.querySelector('.mission-destination-venue');
        var province = root.querySelector('.mission-destination-province');
        var district = root.querySelector('.mission-destination-district');
        var textField = root.querySelector('.mission-destination-text');
        if (!textField || textField.dataset.touched === '1') {
            return;
        }
        var parts = [];
        if (venue && venue.value) { parts.push(venue.value); }
        if (district && district.selectedIndex > 0) { parts.push(district.options[district.selectedIndex].textContent); }
        if (province && province.selectedIndex > 0) { parts.push('ខេត្ត ' + province.options[province.selectedIndex].textContent); }
        textField.value = parts.join(', ');
    }

    function initMissionDestinationPicker(root) {
        var url = root.dataset.gazetteerUrl;
        var province = root.querySelector('.mission-destination-province');
        var district = root.querySelector('.mission-destination-district');
        var commune = root.querySelector('.mission-destination-commune');
        var village = root.querySelector('.mission-destination-village');
        var venue = root.querySelector('.mission-destination-venue');
        var textField = root.querySelector('.mission-destination-text');
        if (!province || !district || !commune || !village) {
            return;
        }

        var initialProvince = province.dataset.initial || '';
        var initialDistrict = district.dataset.initial || '';
        var initialCommune = commune.dataset.initial || '';
        var initialVillage = village.dataset.initial || '';

        if (textField) {
            textField.addEventListener('input', function () { textField.dataset.touched = '1'; });
        }
        if (venue) {
            venue.addEventListener('input', function () { composeDestinationText(root); });
        }

        loadGazetteer(url).then(function (provinces) {
            fillSelect(province, provinces, 'ជ្រើសរើសខេត្ត/រាជធានី');
            if (initialProvince) { province.value = initialProvince; }

            function onProvinceChange(keepInitial) {
                var p = findByCode(provinces, province.value);
                fillSelect(district, p ? p.districts : [], 'ជ្រើសរើសស្រុក/ខណ្ឌ');
                fillSelect(commune, [], 'ជ្រើសរើសឃុំ/សង្កាត់');
                fillSelect(village, [], 'ជ្រើសរើសភូមិ');
                if (keepInitial && initialDistrict) { district.value = initialDistrict; }
                onDistrictChange(keepInitial);
            }

            function onDistrictChange(keepInitial) {
                var p = findByCode(provinces, province.value);
                var d = p ? findByCode(p.districts, district.value) : null;
                fillSelect(commune, d ? d.communes : [], 'ជ្រើសរើសឃុំ/សង្កាត់');
                fillSelect(village, [], 'ជ្រើសរើសភូមិ');
                if (keepInitial && initialCommune) { commune.value = initialCommune; }
                onCommuneChange(keepInitial);
            }

            function onCommuneChange(keepInitial) {
                var p = findByCode(provinces, province.value);
                var d = p ? findByCode(p.districts, district.value) : null;
                var c = d ? findByCode(d.communes, commune.value) : null;
                fillSelect(village, c ? c.villages : [], 'ជ្រើសរើសភូមិ');
                if (keepInitial && initialVillage) { village.value = initialVillage; }
                composeDestinationText(root);
            }

            province.addEventListener('change', function () { onProvinceChange(false); });
            district.addEventListener('change', function () { onDistrictChange(false); });
            commune.addEventListener('change', function () { onCommuneChange(false); });
            village.addEventListener('change', function () { composeDestinationText(root); });

            onProvinceChange(true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.mission-destination-picker').forEach(initMissionDestinationPicker);
    });
})();
