function parseAllowedParentMap($form) {
    const rawMap = $form.attr("data-allowed-parent-map");

    if (!rawMap) {
        return {};
    }

    try {
        const parsedMap = JSON.parse(rawMap);
        const normalizedMap = {};

        Object.keys(parsedMap).forEach(function (childTypeId) {
            const values = Array.isArray(parsedMap[childTypeId])
                ? parsedMap[childTypeId]
                : [];

            normalizedMap[String(childTypeId)] = values
                .map(function (value) {
                    return Number(value);
                })
                .filter(function (value) {
                    return Number.isInteger(value) && value > 0;
                });
        });

        return normalizedMap;
    } catch (error) {
        return {};
    }
}

const orgUnitI18n = window.orgUnitI18n || {};

function t(key, fallback) {
    return orgUnitI18n[key] || fallback;
}

function refreshSelectIfEnhanced($select) {
    if ($select.data("select2")) {
        $select.trigger("change.select2");
    }
}

function toggleUnitTypeOptionsForForcedParent($form) {
    const $unitTypeSelect = $form.find(".js-unit-type-select").first();
    const $parentSelect = $form.find(".js-parent-unit-select").first();
    const forcedParentId = Number($form.attr("data-forced-parent-id") || 0);

    if (!$unitTypeSelect.length) {
        return;
    }

    const showAllUnitTypes = function () {
        $unitTypeSelect.find("option").each(function (index, option) {
            option.hidden = false;
            option.disabled = false;
        });
    };

    if (!forcedParentId || !$parentSelect.length) {
        showAllUnitTypes();
        refreshSelectIfEnhanced($unitTypeSelect);
        return;
    }

    const $forcedParentOption = $parentSelect.find('option[value="' + forcedParentId + '"]');
    const parentTypeId = Number($forcedParentOption.data("unit-type-id") || 0);

    if (!parentTypeId) {
        showAllUnitTypes();
        refreshSelectIfEnhanced($unitTypeSelect);
        return;
    }

    const allowedParentMap = parseAllowedParentMap($form);
    const allowedChildTypeIds = Object.keys(allowedParentMap)
        .map(function (childTypeId) {
            return Number(childTypeId);
        })
        .filter(function (childTypeId) {
            const parentTypeIds = allowedParentMap[String(childTypeId)] || [];
            return parentTypeIds.includes(parentTypeId);
        });

    if (!allowedChildTypeIds.length) {
        showAllUnitTypes();
        refreshSelectIfEnhanced($unitTypeSelect);
        return;
    }

    const selectedUnitTypeId = Number($unitTypeSelect.val() || 0);
    let selectedStillValid = false;
    let firstAllowedValue = "";

    $unitTypeSelect.find("option").each(function (index, option) {
        if (index === 0) {
            option.hidden = false;
            option.disabled = false;
            return;
        }

        const optionTypeId = Number(option.value || 0);
        const isAllowed = allowedChildTypeIds.includes(optionTypeId);

        option.hidden = !isAllowed;
        option.disabled = !isAllowed;

        if (isAllowed && !firstAllowedValue) {
            firstAllowedValue = String(optionTypeId);
        }

        if (isAllowed && optionTypeId === selectedUnitTypeId) {
            selectedStillValid = true;
        }
    });

    if (selectedUnitTypeId && !selectedStillValid) {
        $unitTypeSelect.val(firstAllowedValue || "");
    }

    refreshSelectIfEnhanced($unitTypeSelect);
}

function toggleParentUnitOptions($form) {
    const $unitTypeSelect = $form.find(".js-unit-type-select").first();
    const $parentSelect = $form.find(".js-parent-unit-select").first();
    const $parentHint = $form.find(".js-parent-unit-hint").first();
    const forcedParentId = Number($form.attr("data-forced-parent-id") || 0);

    if (!$unitTypeSelect.length || !$parentSelect.length) {
        return;
    }

    toggleUnitTypeOptionsForForcedParent($form);

    const allowedParentMap = parseAllowedParentMap($form);
    const childTypeId = Number($unitTypeSelect.val() || 0);
    const selectedParentId = String($parentSelect.val() || "");
    let selectedParentStillVisible = false;

    const setHint = function (message) {
        if ($parentHint.length) {
            $parentHint.text(message);
        }
    };

    const showAllParentOptions = function () {
        $parentSelect.find("option").each(function (index, option) {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            option.hidden = false;
            option.disabled = false;
        });
    };

    if (!childTypeId) {
        if (forcedParentId > 0) {
            $parentSelect.val(String(forcedParentId));
        } else {
            $parentSelect.val("");
        }
        showAllParentOptions();
        $parentSelect.prop("disabled", true);
        setHint(t("chooseUnitTypeFirst", "Choose unit type first."));
        refreshSelectIfEnhanced($parentSelect);
        return;
    }

    const allowedParentTypeIds = allowedParentMap[String(childTypeId)] || [];

    if (!allowedParentTypeIds.length) {
        $parentSelect.val("");
        $parentSelect.find("option").each(function (index, option) {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            option.hidden = true;
            option.disabled = true;
        });
        $parentSelect.prop("disabled", true);
        setHint(t("topLevelNoParent", "This unit type is top-level (no parent required)."));
        refreshSelectIfEnhanced($parentSelect);
        return;
    }

    $parentSelect.find("option").each(function (index, option) {
        if (index === 0) {
            option.hidden = false;
            option.disabled = false;
            return;
        }

        const optionTypeId = Number($(option).data("unit-type-id") || 0);
        const isAllowed = allowedParentTypeIds.includes(optionTypeId);

        option.hidden = !isAllowed;
        option.disabled = !isAllowed;

        if (isAllowed && String(option.value) === selectedParentId) {
            selectedParentStillVisible = true;
        }
    });

    if (selectedParentId && !selectedParentStillVisible) {
        const forcedParentStillAllowed = forcedParentId > 0
            && allowedParentTypeIds.includes(
                Number($parentSelect.find('option[value="' + forcedParentId + '"]').data("unit-type-id") || 0)
            );

        if (forcedParentStillAllowed) {
            $parentSelect.val(String(forcedParentId));
        } else {
            $parentSelect.val("");
        }
    } else if (!selectedParentId && forcedParentId > 0) {
        const $forcedOption = $parentSelect.find('option[value="' + forcedParentId + '"]');
        const forcedOptionTypeId = Number($forcedOption.data("unit-type-id") || 0);
        if ($forcedOption.length && allowedParentTypeIds.includes(forcedOptionTypeId)) {
            $parentSelect.val(String(forcedParentId));
        }
    }

    $parentSelect.prop("disabled", false);
    setHint(t("selectParentOrLeaveBlank", "You can select any existing org unit as parent, or leave blank for root."));
    refreshSelectIfEnhanced($parentSelect);
}

function bindOrgUnitParentFilter($scope) {
    $scope.find("form.js-org-unit-form").each(function () {
        const $form = $(this);
        const $unitTypeSelect = $form.find(".js-unit-type-select").first();

        if (!$unitTypeSelect.length) {
            return;
        }

        $unitTypeSelect
            .off("change.orgUnitParentFilter")
            .on("change.orgUnitParentFilter", function () {
                toggleParentUnitOptions($form);
            });

        toggleParentUnitOptions($form);
    });
}

function editDetails(uuid) {
    let editBtn = $("#editDetails-" + uuid);
    let editUrl = editBtn.data("edit-url");

    $.ajax({
        type: "GET",
        dataType: "html",
        url: editUrl,
        success: function (data) {
            const $modal = $("#edit-department");
            $modal.find(".modal-content").html(data);
            bindOrgUnitParentFilter($modal);
            $modal.modal("show");
        },
    });
}

$(function () {
    bindOrgUnitParentFilter($(document));
});

$(document).on("shown.bs.modal", ".modal", function () {
    bindOrgUnitParentFilter($(this));
});
