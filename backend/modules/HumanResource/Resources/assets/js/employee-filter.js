$(document).ready(function () {
    var $table = $("#employee-table");
    var $department = $("#department");

    var $combo = $("#department-tree-combo");
    var $comboToggle = $("#department-tree-combo-toggle");
    var $comboLabel = $("#department-tree-combo-label");
    var $comboPanel = $("#department-tree-panel");
    var $comboSearch = $("#department-tree-search");
    var $comboClear = $("#department-tree-clear");

    var allDepartmentLabel = (($combo.data("allLabel") || $department.find("option:first").text() || "") + "").trim();

    function normalizedValue(selector) {
        var value = $(selector).val();
        if (value === undefined || value === null) {
            return "";
        }
        return value;
    }

    function collectFilterPayload() {
        return {
            employee_name: normalizedValue("#employee_name"),
            department: normalizedValue("#department"),
            designation: normalizedValue("#designation"),
            official_id_10: normalizedValue("#official_id_10"),
            work_status_name: normalizedValue("#work_status_name"),
            employee_status: normalizedValue("#employee_status"),
            gender: normalizedValue("#gender"),
        };
    }

    function applyFilterAndReload(payload) {
        $table.off("preXhr.dt.employeeFilter");
        $table.on("preXhr.dt.employeeFilter", function (e, settings, data) {
            Object.keys(payload).forEach(function (key) {
                data[key] = payload[key];
            });
        });
        $table.DataTable().ajax.reload();
    }

    function setTreeOpenState($item, isOpen) {
        if (!$item || !$item.length || !$item.hasClass("has-children")) {
            return;
        }

        $item.toggleClass("is-open", isOpen);
        var $toggle = $item.children(".employee-org-tree-row").find(".employee-org-tree-toggle").first();
        if ($toggle.length) {
            $toggle.attr("aria-expanded", isOpen ? "true" : "false");
            $toggle.find(".toggle-symbol").text(isOpen ? "-" : "+");
        }
    }

    function closeAllTreeItems() {
        if (!$comboPanel.length) {
            return;
        }
        $comboPanel.find(".employee-org-tree-item.has-children").each(function () {
            setTreeOpenState($(this), false);
        });
    }

    function markTreeSelection(unitId) {
        if (!$comboPanel.length) {
            return;
        }

        var selectedUnitId = ((unitId || "") + "").trim();
        $comboPanel.find(".employee-org-tree-node-filter.is-active").removeClass("is-active");

        if (!selectedUnitId) {
            $comboLabel.text(allDepartmentLabel);
            return;
        }

        var $selectedNode = $comboPanel.find('.employee-org-tree-node-filter[data-org-unit-id="' + selectedUnitId + '"]').first();
        if (!$selectedNode.length) {
            $comboLabel.text(allDepartmentLabel);
            return;
        }

        $selectedNode.addClass("is-active");
        $selectedNode.parents(".employee-org-tree-item").each(function () {
            setTreeOpenState($(this), true);
        });

        var name = ($selectedNode.find(".employee-org-tree-name").first().text() || "").trim();
        $comboLabel.text(name || allDepartmentLabel);
    }

    function selectDepartment(unitId) {
        $department.val(unitId).trigger("change");
        markTreeSelection(unitId);
    }

    function openCombo() {
        if ($combo.length) {
            $combo.addClass("is-open");
        }
    }

    function closeCombo() {
        if ($combo.length) {
            $combo.removeClass("is-open");
        }
    }

    function filterTree(keyword) {
        if (!$comboPanel.length) {
            return;
        }

        var term = ((keyword || "") + "").trim().toLowerCase();

        if (!term) {
            $comboPanel.find(".employee-org-tree-item").show();
            closeAllTreeItems();
            markTreeSelection($department.val());
            return;
        }

        closeAllTreeItems();
        $comboPanel.find(".employee-org-tree-item").hide();

        $comboPanel.find(".employee-org-tree-node-filter").each(function () {
            var $node = $(this);
            var text = ($node.text() || "").toLowerCase();
            if (text.indexOf(term) === -1) {
                return;
            }

            var $item = $node.closest(".employee-org-tree-item");
            $item.show();
            $item.parents(".employee-org-tree-item").show().each(function () {
                setTreeOpenState($(this), true);
            });
            $item.find(".employee-org-tree-item").show();
            setTreeOpenState($item, true);
        });
    }

    function initDepartmentTreeCombo() {
        if (!$combo.length || !$comboPanel.length || !$department.length) {
            return;
        }

        $comboPanel.find(".employee-org-tree-toggle").each(function () {
            $(this).attr("aria-expanded", "false").find(".toggle-symbol").text("+");
        });

        $comboToggle.on("click", function (event) {
            event.preventDefault();
            if ($combo.hasClass("is-open")) {
                closeCombo();
            } else {
                openCombo();
                if ($comboSearch.length) {
                    $comboSearch.trigger("focus");
                }
            }
        });

        $(document).on("click", function (event) {
            if (!$combo.length) {
                return;
            }
            if (!$combo.is(event.target) && $combo.has(event.target).length === 0) {
                closeCombo();
            }
        });

        $comboPanel.on("click", ".employee-org-tree-toggle", function (event) {
            event.preventDefault();
            event.stopPropagation();
            var $item = $(this).closest(".employee-org-tree-item");
            setTreeOpenState($item, !$item.hasClass("is-open"));
        });

        $comboPanel.on("click", ".employee-org-tree-node-filter", function (event) {
            event.preventDefault();
            var unitId = ((($(this).data("orgUnitId") || "") + "").trim());
            selectDepartment(unitId);
            closeCombo();
        });

        if ($comboClear.length) {
            $comboClear.on("click", function (event) {
                event.preventDefault();
                if ($comboSearch.length) {
                    $comboSearch.val("");
                }
                filterTree("");
                selectDepartment("");
            });
        }

        if ($comboSearch.length) {
            $comboSearch.on("input", function () {
                filterTree($(this).val());
            });
        }

        markTreeSelection($department.val());
    }

    $("#filter").on("click", function () {
        applyFilterAndReload(collectFilterPayload());
    });

    $("#search-reset").on("click", function () {
        $("#employee_name").val("").trigger("change");
        $("#department").val("").trigger("change");
        $("#designation").val("").trigger("change");
        $("#official_id_10").val("").trigger("change");
        $("#work_status_name").val("").trigger("change");
        $("#employee_status").val("").trigger("change");
        $("#gender").val("").trigger("change");

        if ($comboSearch.length) {
            $comboSearch.val("");
        }
        filterTree("");
        selectDepartment("");
        closeCombo();

        applyFilterAndReload({
            employee_name: "",
            department: "",
            designation: "",
            official_id_10: "",
            work_status_name: "",
            employee_status: "",
            gender: "",
        });
    });

    initDepartmentTreeCombo();
});




