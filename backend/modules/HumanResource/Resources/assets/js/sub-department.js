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

function toggleParentUnitOptions($form) {
    const $unitTypeSelect = $form.find(".js-unit-type-select").first();
    const $parentSelect = $form.find(".js-parent-unit-select").first();
    const $parentHint = $form.find(".js-parent-unit-hint").first();

    if (!$unitTypeSelect.length || !$parentSelect.length) {
        return;
    }

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
        $parentSelect.val("");
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
        $parentSelect.val("");
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
            const $modal = $("#edit-sub-department");
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
