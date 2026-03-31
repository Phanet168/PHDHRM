(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function safeText(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim();
    }

    function nodeLabel(node) {
        return safeText(node.name || node.khmer || node.latin || node.label || '');
    }

    function option(el, value, label) {
        var opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        el.appendChild(opt);
        return opt;
    }

    function clearAndPlaceholder(selectEl, placeholder) {
        while (selectEl.firstChild) {
            selectEl.removeChild(selectEl.firstChild);
        }
        option(selectEl, '', placeholder);
    }

    function findPathById(nodes, targetId) {
        if (!Array.isArray(nodes) || !targetId) return [];
        for (var i = 0; i < nodes.length; i++) {
            var n = nodes[i];
            if (String(n.id) === String(targetId)) {
                return [n];
            }
            var childPath = findPathById(n.children || [], targetId);
            if (childPath.length) {
                childPath.unshift(n);
                return childPath;
            }
        }
        return [];
    }

    function initOrgUnitCascade() {
        var container = byId('org-unit-cascade');
        var targetSelect = byId('department');
        if (!container || !targetSelect) {
            return;
        }

        var config = window.employeeCascadeConfig || {};
        var tree = Array.isArray(config.orgUnitTree) ? config.orgUnitTree : [];
        if (!tree.length) {
            return;
        }

        targetSelect.classList.add('d-none');
        targetSelect.classList.remove('org-unit-fallback');

        var initialId = container.dataset.initial || targetSelect.value || '';
        var path = findPathById(tree, initialId);

        function renderLevel(nodes, level, selectedId) {
            if (!Array.isArray(nodes) || !nodes.length) return;

            var select = document.createElement('select');
            select.className = 'form-select mb-2';
            select.dataset.level = String(level);
            clearAndPlaceholder(select, level === 0 ? 'Select organization unit' : 'Select sub-unit');

            nodes.forEach(function (node) {
                var label = nodeLabel(node);
                option(select, String(node.id), label || ('Unit #' + node.id));
            });

            if (selectedId) {
                select.value = String(selectedId);
            }

            select.addEventListener('change', function () {
                var thisLevel = Number(this.dataset.level || 0);
                var selectedValue = this.value;

                Array.prototype.slice.call(container.querySelectorAll('select')).forEach(function (s) {
                    if (Number(s.dataset.level || 0) > thisLevel) {
                        s.remove();
                    }
                });

                targetSelect.value = selectedValue || '';

                if (!selectedValue) {
                    return;
                }

                var selectedNode = nodes.find(function (n) {
                    return String(n.id) === String(selectedValue);
                });

                if (selectedNode && Array.isArray(selectedNode.children) && selectedNode.children.length) {
                    renderLevel(selectedNode.children, thisLevel + 1, '');
                }
            });

            container.appendChild(select);

            if (select.value) {
                targetSelect.value = select.value;
            }
        }

        container.innerHTML = '';

        if (path.length) {
            var levelNodes = tree;
            for (var i = 0; i < path.length; i++) {
                renderLevel(levelNodes, i, path[i].id);
                levelNodes = path[i].children || [];
            }
        } else {
            renderLevel(tree, 0, targetSelect.value || '');
        }
    }

    function getName(item) {
        return safeText(item.khmer || item.latin || item.name || '');
    }

    function getCode(item) {
        return safeText(item.code || item.id || '');
    }

    function matchesNode(node, selectedValue) {
        var selected = safeText(selectedValue);
        if (!selected) {
            return false;
        }
        return getCode(node) === selected || getName(node) === selected;
    }

    function findNodeBySelection(nodes, selectedValue) {
        if (!Array.isArray(nodes)) {
            return null;
        }
        for (var i = 0; i < nodes.length; i++) {
            if (matchesNode(nodes[i], selectedValue)) {
                return nodes[i];
            }
        }
        return null;
    }

    function setSelectByValueOrLabel(selectEl, rawValue) {
        var selected = safeText(rawValue);
        if (!selected || !selectEl) {
            return;
        }

        var options = selectEl.options || [];
        for (var i = 0; i < options.length; i++) {
            if (safeText(options[i].value) === selected || safeText(options[i].textContent) === selected) {
                selectEl.value = options[i].value;
                return;
            }
        }
    }

    function selectedOptionLabel(selectEl) {
        if (!selectEl || selectEl.selectedIndex < 0) {
            return '';
        }
        var selectedOption = selectEl.options[selectEl.selectedIndex];
        if (!selectedOption || safeText(selectedOption.value) === '') {
            return '';
        }
        return safeText(selectedOption.textContent);
    }

    function buildAddressText() {
        var parts = [
            selectedOptionLabel(byId('present_address_state')),
            selectedOptionLabel(byId('present_address_city')),
            selectedOptionLabel(byId('present_address_post_code')),
            selectedOptionLabel(byId('present_address_address'))
        ].filter(Boolean);

        var stateNameInput = byId('present_address_state_name');
        if (stateNameInput) {
            stateNameInput.value = selectedOptionLabel(byId('present_address_state'));
        }
        var cityNameInput = byId('present_address_city_name');
        if (cityNameInput) {
            cityNameInput.value = selectedOptionLabel(byId('present_address_city'));
        }
        var communeNameInput = byId('present_address_commune_name');
        if (communeNameInput) {
            communeNameInput.value = selectedOptionLabel(byId('present_address_post_code'));
        }
        var villageNameInput = byId('present_address_village_name');
        if (villageNameInput) {
            villageNameInput.value = selectedOptionLabel(byId('present_address_address'));
        }

        var fullAddress = byId('present_address');
        if (fullAddress) {
            fullAddress.value = parts.length > 0 ? parts.join(' > ') : '';
        }
    }

    function initKhAddressCascade() {
        var wrapper = byId('kh-address-cascade');
        if (!wrapper) {
            return;
        }

        var provinceSelect = byId('present_address_state');
        var districtSelect = byId('present_address_city');
        var communeSelect = byId('present_address_post_code');
        var villageSelect = byId('present_address_address');

        if (!provinceSelect || !districtSelect || !communeSelect || !villageSelect) {
            return;
        }

        var initialProvince = provinceSelect.dataset.initial || '';
        var initialDistrict = districtSelect.dataset.initial || '';
        var initialCommune = communeSelect.dataset.initial || '';
        var initialVillage = villageSelect.dataset.initial || '';

        var sourceUrl = wrapper.dataset.sourceUrl;
        if (!sourceUrl) {
            return;
        }

        fetch(sourceUrl)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var provinces = Array.isArray(data) ? data : [];
                var placeholderProvince = safeText(wrapper.dataset.placeholderProvince) || 'Select province/capital';
                var placeholderDistrict = safeText(wrapper.dataset.placeholderDistrict) || 'Select city/district/khan';
                var placeholderCommune = safeText(wrapper.dataset.placeholderCommune) || 'Select commune/sangkat';
                var placeholderVillage = safeText(wrapper.dataset.placeholderVillage) || 'Select village';

                function populateProvinces() {
                    clearAndPlaceholder(provinceSelect, placeholderProvince);
                    provinces.forEach(function (p) {
                        option(provinceSelect, getCode(p) || getName(p), getName(p));
                    });
                    setSelectByValueOrLabel(provinceSelect, initialProvince);
                    onProvinceChange();
                }

                function onProvinceChange() {
                    clearAndPlaceholder(districtSelect, placeholderDistrict);
                    clearAndPlaceholder(communeSelect, placeholderCommune);
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildAddressText();
                        return;
                    }

                    (province.districts || []).forEach(function (d) {
                        option(districtSelect, getCode(d) || getName(d), getName(d));
                    });

                    if (initialDistrict && !districtSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(districtSelect, initialDistrict);
                        districtSelect.dataset.initialApplied = '1';
                    }

                    onDistrictChange();
                }

                function onDistrictChange() {
                    clearAndPlaceholder(communeSelect, placeholderCommune);
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildAddressText();
                        return;
                    }

                    var district = findNodeBySelection((province.districts || []), districtSelect.value);
                    if (!district) {
                        buildAddressText();
                        return;
                    }

                    (district.communes || []).forEach(function (c) {
                        option(communeSelect, getCode(c) || getName(c), getName(c));
                    });

                    if (initialCommune && !communeSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(communeSelect, initialCommune);
                        communeSelect.dataset.initialApplied = '1';
                    }

                    onCommuneChange();
                }

                function onCommuneChange() {
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildAddressText();
                        return;
                    }

                    var district = findNodeBySelection((province.districts || []), districtSelect.value);
                    if (!district) {
                        buildAddressText();
                        return;
                    }

                    var commune = findNodeBySelection((district.communes || []), communeSelect.value);
                    if (!commune) {
                        buildAddressText();
                        return;
                    }

                    (commune.villages || []).forEach(function (v) {
                        option(villageSelect, getCode(v) || getName(v), getName(v));
                    });

                    if (initialVillage && !villageSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(villageSelect, initialVillage);
                        villageSelect.dataset.initialApplied = '1';
                    }

                    buildAddressText();
                }

                provinceSelect.addEventListener('change', onProvinceChange);
                districtSelect.addEventListener('change', onDistrictChange);
                communeSelect.addEventListener('change', onCommuneChange);
                villageSelect.addEventListener('change', buildAddressText);

                populateProvinces();
            })
            .catch(function () {
                // Keep default form usable if loading data fails.
            });
    }

    function buildBirthPlaceText() {
        var parts = [
            selectedOptionLabel(byId('birth_place_state')),
            selectedOptionLabel(byId('birth_place_city')),
            selectedOptionLabel(byId('birth_place_commune')),
            selectedOptionLabel(byId('birth_place_village'))
        ].filter(Boolean);

        var stateNameInput = byId('birth_place_state_name');
        if (stateNameInput) {
            stateNameInput.value = selectedOptionLabel(byId('birth_place_state'));
        }
        var cityNameInput = byId('birth_place_city_name');
        if (cityNameInput) {
            cityNameInput.value = selectedOptionLabel(byId('birth_place_city'));
        }
        var communeNameInput = byId('birth_place_commune_name');
        if (communeNameInput) {
            communeNameInput.value = selectedOptionLabel(byId('birth_place_commune'));
        }
        var villageNameInput = byId('birth_place_village_name');
        if (villageNameInput) {
            villageNameInput.value = selectedOptionLabel(byId('birth_place_village'));
        }

        var legacyField = byId('legacy_pob_code');
        if (legacyField) {
            legacyField.value = parts.length > 0 ? parts.join(' > ') : '';
        }
    }

    function initKhBirthCascade() {
        var wrapper = byId('kh-birth-cascade');
        if (!wrapper) {
            return;
        }

        var provinceSelect = byId('birth_place_state');
        var districtSelect = byId('birth_place_city');
        var communeSelect = byId('birth_place_commune');
        var villageSelect = byId('birth_place_village');

        if (!provinceSelect || !districtSelect || !communeSelect || !villageSelect) {
            return;
        }

        var initialProvince = provinceSelect.dataset.initial || '';
        var initialDistrict = districtSelect.dataset.initial || '';
        var initialCommune = communeSelect.dataset.initial || '';
        var initialVillage = villageSelect.dataset.initial || '';

        var sourceUrl = wrapper.dataset.sourceUrl;
        if (!sourceUrl) {
            return;
        }

        fetch(sourceUrl)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var provinces = Array.isArray(data) ? data : [];
                var placeholderProvince = safeText(wrapper.dataset.placeholderProvince) || 'Select province/capital';
                var placeholderDistrict = safeText(wrapper.dataset.placeholderDistrict) || 'Select city/district/khan';
                var placeholderCommune = safeText(wrapper.dataset.placeholderCommune) || 'Select commune/sangkat';
                var placeholderVillage = safeText(wrapper.dataset.placeholderVillage) || 'Select village';

                function populateProvinces() {
                    clearAndPlaceholder(provinceSelect, placeholderProvince);
                    provinces.forEach(function (p) {
                        option(provinceSelect, getCode(p) || getName(p), getName(p));
                    });
                    setSelectByValueOrLabel(provinceSelect, initialProvince);
                    onProvinceChange();
                }

                function onProvinceChange() {
                    clearAndPlaceholder(districtSelect, placeholderDistrict);
                    clearAndPlaceholder(communeSelect, placeholderCommune);
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildBirthPlaceText();
                        return;
                    }

                    (province.districts || []).forEach(function (d) {
                        option(districtSelect, getCode(d) || getName(d), getName(d));
                    });

                    if (initialDistrict && !districtSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(districtSelect, initialDistrict);
                        districtSelect.dataset.initialApplied = '1';
                    }

                    onDistrictChange();
                }

                function onDistrictChange() {
                    clearAndPlaceholder(communeSelect, placeholderCommune);
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildBirthPlaceText();
                        return;
                    }

                    var district = findNodeBySelection((province.districts || []), districtSelect.value);
                    if (!district) {
                        buildBirthPlaceText();
                        return;
                    }

                    (district.communes || []).forEach(function (c) {
                        option(communeSelect, getCode(c) || getName(c), getName(c));
                    });

                    if (initialCommune && !communeSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(communeSelect, initialCommune);
                        communeSelect.dataset.initialApplied = '1';
                    }

                    onCommuneChange();
                }

                function onCommuneChange() {
                    clearAndPlaceholder(villageSelect, placeholderVillage);

                    var province = findNodeBySelection(provinces, provinceSelect.value);
                    if (!province) {
                        buildBirthPlaceText();
                        return;
                    }

                    var district = findNodeBySelection((province.districts || []), districtSelect.value);
                    if (!district) {
                        buildBirthPlaceText();
                        return;
                    }

                    var commune = findNodeBySelection((district.communes || []), communeSelect.value);
                    if (!commune) {
                        buildBirthPlaceText();
                        return;
                    }

                    (commune.villages || []).forEach(function (v) {
                        option(villageSelect, getCode(v) || getName(v), getName(v));
                    });

                    if (initialVillage && !villageSelect.dataset.initialApplied) {
                        setSelectByValueOrLabel(villageSelect, initialVillage);
                        villageSelect.dataset.initialApplied = '1';
                    }

                    buildBirthPlaceText();
                }

                provinceSelect.addEventListener('change', onProvinceChange);
                districtSelect.addEventListener('change', onDistrictChange);
                communeSelect.addEventListener('change', onCommuneChange);
                villageSelect.addEventListener('change', buildBirthPlaceText);

                populateProvinces();
            })
            .catch(function () {
                // Keep default form usable if loading data fails.
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initOrgUnitCascade();
        initKhAddressCascade();
        initKhBirthCascade();
    });
})();









