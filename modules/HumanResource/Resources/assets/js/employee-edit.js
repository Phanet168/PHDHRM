"use strict";

var count = $(".employee_docs").length + 1;
var html = "";

function isKhmerLocaleUi() {
    var lang = (($("html").attr("lang") || "") + "").toLowerCase();
    return lang.indexOf("km") === 0;
}

function t(key, fallback) {
    try {
        var translated = localize(key);
        if (translated && translated !== key) {
            return translated;
        }
    } catch (e) {
        // ignore
    }
    return fallback;
}

function kh(en, km) {
    return isKhmerLocaleUi() ? km : en;
}

$("#add_doc_row").click(function (e) {
    e.preventDefault();
    if (count > 5) {
        alert(kh("Maximum 5 documents allowed", "áž¢áž¶áž…áž”áž“áŸ’ážáŸ‚áž˜áž¯áž€ážŸáž¶ážšáž”áž¶áž“áž¢ážáž·áž”ážšáž˜áž¶ 5 áž¯áž€ážŸáž¶ážš"));
        return false;
    }

    html =
        '<div class="row">' +
            '<div class="col-md-4 mb-3">' +
                '<div class="form-group">' +
                    '<label class="mb-2" for="doc-title_' + count + '">' + t("doc_title", "Doc title") + "</label>" +
                    '<input type="text" class="form-control" id="doc-title_' + count + '" placeholder="' + t("doc_title", "Doc title") + '" name="employee_docs[' + count + '][document_title]">' +
                "</div>" +
            "</div>" +
            '<div class="col-md-3 mb-3">' +
                '<label class="mb-2" for="doc_file' + count + '">' + t("file", "File") + "</label>" +
                '<input type="file" class="form-control" id="doc_file' + count + '" name="employee_docs[' + count + '][file]">' +
            "</div>" +
            '<div class="col-md-4 mb-3">' +
                '<div class="form-group">' +
                    '<label class="mb-2" for="expiry_date' + count + '">' + t("expiry_date", "Expiry date") + "</label>" +
                    '<input type="date" class="form-control" id="expiry_date' + count + '" name="employee_docs[' + count + '][expiry_date]">' +
                "</div>" +
            "</div>" +
            '<div class="col-md-1"><span class="align-middle btn btn-danger delete-btn"><i class="fa fa-trash text-white"></i></span></div>' +
        "</div>";

    $("#employee-docs").append(html);
    count++;
});

$(document).on("click", "span.delete-btn", function (e) {
    e.preventDefault();
    $(this).parent().parent().remove();
    count--;
});

function selectedEmployeeTypeKey() {
    var $selected = $("#employee_type_id option:selected");
    if (!$selected.length) {
        return "";
    }

    var fromData = (($selected.data("type") || "") + "").toLowerCase().trim();
    if (fromData) {
        return fromData;
    }

    return (($selected.text() || "") + "").toLowerCase().trim();
}

function selectedEmployeeTypeCadreFlag() {
    var $selected = $("#employee_type_id option:selected");
    if (!$selected.length) {
        return "";
    }

    var raw = $selected.attr("data-cadre");
    var value = raw == null ? "" : ("" + raw).toLowerCase().trim();
    return value === "1" || value === "0" ? value : "";
}

function isCivilServantEmployeeType() {
    var cadreFlag = selectedEmployeeTypeCadreFlag();
    if (cadreFlag !== "") {
        return cadreFlag === "1";
    }

    var key = selectedEmployeeTypeKey();
    if (!key) {
        return false;
    }

    return key === "full time" ||
        key === "civil servant" ||
        key.indexOf("civil") !== -1 ||
        key.indexOf("state cadre") !== -1 ||
        key.indexOf("full time") !== -1 ||
        key.indexOf("áž€áŸ’ážšáž”ážáŸážŽáŸ’ážŒ") !== -1;
}

function isNonCadreEmployeeType() {
    var cadreFlag = selectedEmployeeTypeCadreFlag();
    if (cadreFlag !== "") {
        return cadreFlag === "0";
    }

    var key = selectedEmployeeTypeKey();
    if (!key) {
        return false;
    }

    return key.indexOf("contract") !== -1 ||
        key.indexOf("agreement") !== -1 ||
        key.indexOf("remote") !== -1 ||
        key.indexOf("intern") !== -1 ||
        key.indexOf("កិច្ចសន្យា") !== -1 ||
        key.indexOf("ព្រមព្រៀង") !== -1 ||
        key.indexOf("ជួល") !== -1;
}

function toggleCadreClassificationFields() {
    var disableCadreFields = isNonCadreEmployeeType();
    ["#employee_grade", "#framework_type"].forEach(function (selector) {
        var $field = $(selector);
        if ($field.length === 0) {
            return;
        }

        $field.prop("required", false).prop("disabled", disableCadreFields);
        $field.closest(".form-group").show();
    });
}

function calculateExpectedFullRightDate(serviceStartDate) {
    if (!serviceStartDate) {
        return "";
    }

    var date = new Date(serviceStartDate + "T00:00:00");
    if (isNaN(date.getTime())) {
        return "";
    }

    date.setFullYear(date.getFullYear() + 1);
    var month = String(date.getMonth() + 1).padStart(2, "0");
    var day = String(date.getDate()).padStart(2, "0");
    return date.getFullYear() + "-" + month + "-" + day;
}

function updateCivilServantPhasePreview() {
    var $phase = $("#civil_service_phase_status");
    var $expectedDate = $("#probation_expected_date");
    if (!$phase.length && !$expectedDate.length) {
        return;
    }

    if (!isCivilServantEmployeeType()) {
        $phase.val("");
        $expectedDate.val("");
        return;
    }

    var isFullRight = $("#is_full_right_officer").val() === "1";
    var probationLabel = $phase.data("probationLabel") || kh("Probation civil servant", "áž˜áž“áŸ’ážáŸ’ážšáž¸áž…áž»áŸ‡áž€áž˜áŸ’áž˜ážŸáž·áž€áŸ’ážŸáž¶");
    var fullRightLabel = $phase.data("fullRightLabel") || kh("Full-right civil servant", "áž˜áž“áŸ’ážáŸ’ážšáž¸áž–áŸáž‰ážŸáž·áž‘áŸ’áž’");
    $phase.val(isFullRight ? fullRightLabel : probationLabel);

    var serviceStartDate = $("#service_start_date").val() || $("#joining_date").val();
    $expectedDate.val(calculateExpectedFullRightDate(serviceStartDate));
}

function toggleCivilServantWorkflowFields() {
    toggleFullRightOfficerFields();
    updateCivilServantPhasePreview();
    toggleCadreClassificationFields();
}

function toggleFullRightOfficerFields() {
    var isFullRight = $("#is_full_right_officer").val() === "1";
    var controlledFields = [
        "#full_right_date",
        "#legal_document_type",
        "#legal_document_number",
        "#legal_document_date",
        "#legal_document_subject",
    ];

    controlledFields.forEach(function (selector) {
        var $field = $(selector);
        if ($field.length === 0) {
            return;
        }
        $field.closest(".form-group").show();
        $field.prop("required", isFullRight).prop("disabled", !isFullRight);
    });

    var $officialId = $("#official_id_10");
    if ($officialId.length) {
        $officialId.closest(".form-group").show();
        $officialId.prop("disabled", false).prop("required", isFullRight);
    }
}

function toggleCivilServiceCardFields() {
    var hasCard = $("input[name='work_permit']:checked").val() === "1";
    $(".civil-service-card-fields").prop("hidden", !hasCard);
    $("#card_no, #civil_service_card_expiry_date")
        .prop("required", hasCard)
        .prop("disabled", !hasCard);
}

function toggleMedicalDisabilityFields() {
    var $status = $("#medical_is_disable");
    var $wrapper = $("#medical-disability-description-wrapper");
    var $description = $("#medical_disabilities_desc");
    if (!$status.length || !$wrapper.length || !$description.length) {
        return;
    }

    var hasDisability = ($status.val() || "").toString() === "1";
    $wrapper.prop("hidden", !hasDisability);
    $description.prop("required", hasDisability).prop("disabled", !hasDisability);
}

function toggleEthnicMinorityFields() {
    var $checkbox = $("#is_ethnic_minority");
    var $nameRow = $("#ethnic-minority-name-row");
    var $nameField = $("#ethnic_minority_name");
    var $otherRow = $("#ethnic-minority-other-row");
    var $otherField = $("#ethnic_minority_other");

    if (!$checkbox.length || !$nameRow.length || !$nameField.length || !$otherRow.length || !$otherField.length) {
        return;
    }

    var hasMinority = $checkbox.is(":checked");
    $nameRow.prop("hidden", !hasMinority);
    $nameField.prop("disabled", !hasMinority).prop("required", hasMinority);

    if (!hasMinority) {
        $nameField.val("");
    }

    var selectedValue = (($nameField.val() || "") + "").toLowerCase().trim();
    var isOther = hasMinority && selectedValue === "other";
    $otherRow.prop("hidden", !isOther);
    $otherField.prop("disabled", !isOther).prop("required", isOther);

    if (!isOther) {
        $otherField.val("");
    }
}

function selectedEmployeeGenderKey() {
    var fromData = (($("#gender_id option:selected").data("genderKey") || "") + "").toLowerCase().trim();
    if (fromData === "male" || fromData === "female") {
        return fromData;
    }

    var selectedText = (($("#gender_id option:selected").text() || "") + "").toLowerCase().trim();
    if (!selectedText) {
        return "";
    }

    if (selectedText.indexOf("male") !== -1 || selectedText.indexOf("ប្រុស") !== -1) {
        return "male";
    }
    if (selectedText.indexOf("female") !== -1 || selectedText.indexOf("ស្រី") !== -1) {
        return "female";
    }

    return "";
}

function normalizeFamilyRelationValue(raw) {
    var value = (raw || "").toString().trim().toLowerCase();
    var map = {
        wife: "wife",
        "ប្រពន្ធ": "wife",
        husband: "husband",
        "ប្តី": "husband",
        "ប្ដី": "husband",
        son: "son",
        "កូនប្រុស": "son",
        daughter: "daughter",
        "កូនស្រី": "daughter",
        mother: "mother",
        "ម្តាយបង្កើត": "mother",
        "ម្ដាយបង្កើត": "mother",
        father: "father",
        "ឪពុកបង្កើត": "father",
    };
    return map[value] || value;
}

function familyRelationLabelMap() {
    return {
        wife: t("family_relation_wife", kh("Wife", "ប្រពន្ធ")),
        husband: t("family_relation_husband", kh("Husband", "ប្តី")),
        son: t("family_relation_son", kh("Son", "កូនប្រុស")),
        daughter: t("family_relation_daughter", kh("Daughter", "កូនស្រី")),
        mother: t("family_relation_mother", kh("Biological mother", "ម្តាយបង្កើត")),
        father: t("family_relation_father", kh("Biological father", "ឪពុកបង្កើត")),
    };
}

function familySalutationLabelMap() {
    return {
        boy: t("salutation_boy", kh("Boy", "កុមារា")),
        girl: t("salutation_girl", kh("Girl", "កុមារី")),
        mr: t("salutation_mr", kh("Mr.", "លោក")),
        miss: t("salutation_miss", kh("Ms.", "កញ្ញា")),
        mrs: t("salutation_mrs", kh("Mrs.", "លោកស្រី")),
        excellency: t("salutation_excellency", kh("Excellency", "ឯកឧត្តម")),
        lok_chumteav: t("salutation_lok_chumteav", kh("Lok Chumteav", "លោកជំទាវ")),
    };
}

function familyRelationOptionsHtml(prefix) {
    var labels = familyRelationLabelMap();
    var html = '<select name="' + prefix + '[relation_type]" class="form-select family-relation-type">';
    html += '<option value="">-- ' + t("select_one", kh("Select", "ជ្រើសរើស")) + " --</option>";
    Object.keys(labels).forEach(function (key) {
        html += '<option value="' + key + '">' + labels[key] + "</option>";
    });
    html += "</select>";
    return html;
}

function familySalutationOptionsHtml(prefix) {
    var labels = familySalutationLabelMap();
    var html = '<select name="' + prefix + '[salutation]" class="form-select family-salutation">';
    html += '<option value="">-- ' + t("select_one", kh("Select", "ជ្រើសរើស")) + " --</option>";
    Object.keys(labels).forEach(function (key) {
        html += '<option value="' + key + '">' + labels[key] + "</option>";
    });
    html += "</select>";
    return html;
}

function familyGenderOptionsHtml(prefix) {
    return '<select name="' + prefix + '[gender]" class="form-select family-member-gender">' +
        '<option value="">-</option>' +
    '<option value="male">' + kh("Male", "ប្រុស") + "</option>" +
    '<option value="female">' + kh("Female", "ស្រី") + "</option>" +
        "</select>";
}

var familyBirthGazetteerCache = null;
var familyBirthGazetteerPromise = null;

function familyBirthCascadeConfig() {
    var node = document.getElementById("family-birth-cascade-config");
    return {
        sourceUrl: node ? (node.getAttribute("data-source-url") || "") : "",
        placeholders: {
            province: node ? (node.getAttribute("data-placeholder-province") || "") : "",
            district: node ? (node.getAttribute("data-placeholder-district") || "") : "",
            commune: node ? (node.getAttribute("data-placeholder-commune") || "") : "",
            village: node ? (node.getAttribute("data-placeholder-village") || "") : "",
        },
        aliveLabel: node ? (node.getAttribute("data-label-alive") || "") : "",
        deceasedLabel: node ? (node.getAttribute("data-label-deceased") || "") : "",
    };
}

function familyDeceasedLabels() {
    var cfg = familyBirthCascadeConfig();
    return {
        alive: (cfg.aliveLabel || "").trim() || "Alive",
        deceased: (cfg.deceasedLabel || "").trim() || "Deceased",
    };
}

function familyBirthSelectHtml(prefix, fieldName, fieldClass, placeholderKey) {
    var cfg = familyBirthCascadeConfig();
    var placeholders = cfg.placeholders || {};
    var defaultPlaceholder = "-- " + t("select_one", kh("Select", "Select")) + " --";
    var placeholder = placeholders[placeholderKey] || defaultPlaceholder;

    return '<select name="' + prefix + '[' + fieldName + ']" class="form-select ' + fieldClass + '" data-initial="">' +
        '<option value="">' + placeholder + "</option>" +
        "</select>";
}

function familyBirthSafeText(value) {
    if (value === null || value === undefined) {
        return "";
    }
    return (value + "").trim();
}

function familyBirthName(item) {
    return familyBirthSafeText(item && (item.khmer || item.name || item.latin));
}

function familyBirthCode(item) {
    return familyBirthSafeText(item && (item.code || item.id));
}

function familyBirthSelectValue(item) {
    return familyBirthName(item) || familyBirthCode(item);
}

function familyBirthClearSelect($select, placeholder) {
    if (!$select.length) {
        return;
    }
    $select.empty();
    $select.append($("<option>", { value: "", text: placeholder || "" }));
}

function familyBirthSetSelectByValueOrLabel($select, rawValue) {
    var target = familyBirthSafeText(rawValue);
    if (!target || !$select.length) {
        return;
    }

    var matched = false;
    $select.find("option").each(function () {
        if (matched) {
            return;
        }
        var optionValue = familyBirthSafeText($(this).val());
        var optionLabel = familyBirthSafeText($(this).text());
        if (optionValue === target || optionLabel === target) {
            $select.val($(this).val());
            matched = true;
        }
    });
}

function familyBirthFindNodeBySelection(nodes, selectedValue) {
    var selected = familyBirthSafeText(selectedValue);
    if (!Array.isArray(nodes) || !selected) {
        return null;
    }
    for (var i = 0; i < nodes.length; i++) {
        var node = nodes[i];
        if (familyBirthCode(node) === selected || familyBirthName(node) === selected) {
            return node;
        }
    }
    return null;
}

function loadFamilyBirthGazetteer() {
    if (Array.isArray(familyBirthGazetteerCache)) {
        return $.Deferred().resolve(familyBirthGazetteerCache).promise();
    }

    if (familyBirthGazetteerPromise) {
        return familyBirthGazetteerPromise;
    }

    var cfg = familyBirthCascadeConfig();
    var sourceUrl = (cfg.sourceUrl || "").trim();
    var dfd = $.Deferred();

    if (!sourceUrl) {
        familyBirthGazetteerCache = [];
        dfd.resolve(familyBirthGazetteerCache);
        familyBirthGazetteerPromise = dfd.promise();
        return familyBirthGazetteerPromise;
    }

    $.getJSON(sourceUrl)
        .done(function (data) {
            familyBirthGazetteerCache = Array.isArray(data) ? data : [];
            dfd.resolve(familyBirthGazetteerCache);
        })
        .fail(function () {
            familyBirthGazetteerCache = [];
            dfd.resolve(familyBirthGazetteerCache);
        });

    familyBirthGazetteerPromise = dfd.promise();
    return familyBirthGazetteerPromise;
}

function initFamilyBirthCascadeRow($row) {
    if (!$row || !$row.length || $row.data("familyBirthReady")) {
        return;
    }

    var $province = $row.find(".family-birth-province");
    var $district = $row.find(".family-birth-district");
    var $commune = $row.find(".family-birth-commune");
    var $village = $row.find(".family-birth-village");

    if (!$province.length || !$district.length || !$commune.length || !$village.length) {
        return;
    }

    var cfg = familyBirthCascadeConfig();
    var placeholders = cfg.placeholders || {};
    var defaultPlaceholder = "-- " + t("select_one", kh("Select", "Select")) + " --";

    var provinceInitial = familyBirthSafeText($province.data("initial") || $province.val());
    var districtInitial = familyBirthSafeText($district.data("initial") || $district.val());
    var communeInitial = familyBirthSafeText($commune.data("initial") || $commune.val());
    var villageInitial = familyBirthSafeText($village.data("initial") || $village.val());

    loadFamilyBirthGazetteer().done(function (provinces) {
        var districtInitialApplied = false;
        var communeInitialApplied = false;
        var villageInitialApplied = false;

        function onProvinceChange() {
            familyBirthClearSelect($district, placeholders.district || defaultPlaceholder);
            familyBirthClearSelect($commune, placeholders.commune || defaultPlaceholder);
            familyBirthClearSelect($village, placeholders.village || defaultPlaceholder);

            var province = familyBirthFindNodeBySelection(provinces, $province.val());
            if (!province) {
                return;
            }

            (province.districts || []).forEach(function (district) {
                $district.append($("<option>", {
                    value: familyBirthSelectValue(district),
                    text: familyBirthName(district),
                }));
            });

            if (!districtInitialApplied && districtInitial) {
                familyBirthSetSelectByValueOrLabel($district, districtInitial);
                districtInitialApplied = true;
            }

            onDistrictChange();
        }

        function onDistrictChange() {
            familyBirthClearSelect($commune, placeholders.commune || defaultPlaceholder);
            familyBirthClearSelect($village, placeholders.village || defaultPlaceholder);

            var province = familyBirthFindNodeBySelection(provinces, $province.val());
            if (!province) {
                return;
            }

            var district = familyBirthFindNodeBySelection(province.districts || [], $district.val());
            if (!district) {
                return;
            }

            (district.communes || []).forEach(function (commune) {
                $commune.append($("<option>", {
                    value: familyBirthSelectValue(commune),
                    text: familyBirthName(commune),
                }));
            });

            if (!communeInitialApplied && communeInitial) {
                familyBirthSetSelectByValueOrLabel($commune, communeInitial);
                communeInitialApplied = true;
            }

            onCommuneChange();
        }

        function onCommuneChange() {
            familyBirthClearSelect($village, placeholders.village || defaultPlaceholder);

            var province = familyBirthFindNodeBySelection(provinces, $province.val());
            if (!province) {
                return;
            }

            var district = familyBirthFindNodeBySelection(province.districts || [], $district.val());
            if (!district) {
                return;
            }

            var commune = familyBirthFindNodeBySelection(district.communes || [], $commune.val());
            if (!commune) {
                return;
            }

            (commune.villages || []).forEach(function (village) {
                $village.append($("<option>", {
                    value: familyBirthSelectValue(village),
                    text: familyBirthName(village),
                }));
            });

            if (!villageInitialApplied && villageInitial) {
                familyBirthSetSelectByValueOrLabel($village, villageInitial);
                villageInitialApplied = true;
            }
        }

        $province.off("change.familyBirthCascade").on("change.familyBirthCascade", onProvinceChange);
        $district.off("change.familyBirthCascade").on("change.familyBirthCascade", onDistrictChange);
        $commune.off("change.familyBirthCascade").on("change.familyBirthCascade", onCommuneChange);

        familyBirthClearSelect($province, placeholders.province || defaultPlaceholder);
        (provinces || []).forEach(function (province) {
            $province.append($("<option>", {
                value: familyBirthSelectValue(province),
                text: familyBirthName(province),
            }));
        });

        familyBirthSetSelectByValueOrLabel($province, provinceInitial);
        onProvinceChange();
        $row.data("familyBirthReady", true);
    });
}

function initFamilyBirthCascades() {
    $("#family-members-table tbody tr").each(function () {
        initFamilyBirthCascadeRow($(this));
    });
}

function isSingleMaritalStatusSelected() {
    var key = ($("#marital_status_id option:selected").data("statusKey") || "") + "";
    return key === "single";
}

function updateWidowedMaritalLabel() {
    var $widowedOption = $("#marital_status_id option[data-status-key='widowed']");
    if (!$widowedOption.length) {
        return;
    }

    var gender = selectedEmployeeGenderKey();
    var labelMale = (($widowedOption.data("labelMale") || "") + "").trim();
    var labelFemale = (($widowedOption.data("labelFemale") || "") + "").trim();

    if (gender === "male" && labelMale) {
        $widowedOption.text(labelMale);
    } else if (gender === "female" && labelFemale) {
        $widowedOption.text(labelFemale);
    } else if (labelFemale || labelMale) {
        $widowedOption.text(labelFemale || labelMale);
    }
}

function allowedSalutationsByRelationAndGender(relationValue, genderKey) {
    var relation = normalizeFamilyRelationValue(relationValue);

    if (relation === "mother") {
        return ["mrs", "lok_chumteav"];
    }
    if (relation === "father") {
        return ["mr", "excellency"];
    }
    if (genderKey === "male") {
        return ["boy", "mr", "excellency"];
    }
    if (genderKey === "female") {
        return ["girl", "miss", "mrs", "lok_chumteav"];
    }

    return null;
}

function filterFamilySalutationByGender($salutation, relationValue, genderKey) {
    if (!$salutation.length) {
        return;
    }

    var allowed = allowedSalutationsByRelationAndGender(relationValue, genderKey);

    $salutation.find("option").each(function () {
        var value = ($(this).attr("value") || "").toString();
        if (!value) {
            $(this).prop("disabled", false);
            return;
        }

        if (Array.isArray(allowed)) {
            $(this).prop("disabled", allowed.indexOf(value) === -1);
        } else {
            $(this).prop("disabled", false);
        }
    });

    var current = ($salutation.val() || "").toString();
    if (current && $salutation.find('option[value="' + current + '"]').prop("disabled")) {
        $salutation.val("");
    }
}

function updateFamilySummaryCounts() {
    var spouseCount = 0;
    var kidsCount = 0;
    var isSingle = isSingleMaritalStatusSelected();

    $("#family-members-table tbody tr").each(function () {
        var relation = normalizeFamilyRelationValue($(this).find(".family-relation-type").val());
        if (relation === "wife" || relation === "husband") {
            spouseCount++;
        }
        if (relation === "son" || relation === "daughter") {
            kidsCount++;
        }
    });

    if (isSingle) {
        spouseCount = 0;
        kidsCount = 0;
    }

    $("#spouse_count").val(spouseCount);
    $("#no_of_kids").val(kidsCount);
}

function applyFamilySectionRules() {
    var isSingle = isSingleMaritalStatusSelected();
    var employeeGender = selectedEmployeeGenderKey();

    $("#spouse_count, #no_of_kids").prop("readonly", true);

    $("#family-members-table tbody tr").each(function () {
        var $row = $(this);
        var $relation = $row.find(".family-relation-type");
        var $memberGender = $row.find(".family-member-gender");
        var $salutation = $row.find(".family-salutation");

        if (!$relation.length) {
            return;
        }

        $relation.find("option").prop("disabled", false);

        if (isSingle) {
            $relation.find("option[value='wife'], option[value='husband'], option[value='son'], option[value='daughter']")
                .prop("disabled", true);
        } else {
            if (employeeGender === "male") {
                $relation.find("option[value='husband']").prop("disabled", true);
            } else if (employeeGender === "female") {
                $relation.find("option[value='wife']").prop("disabled", true);
            }
        }

        var relationValue = normalizeFamilyRelationValue($relation.val());
        if (relationValue && $relation.find('option[value="' + relationValue + '"]').prop("disabled")) {
            $relation.val("");
            relationValue = "";
        }

        if (relationValue === "wife" || relationValue === "daughter" || relationValue === "mother") {
            $memberGender.val("female");
        } else if (relationValue === "husband" || relationValue === "son" || relationValue === "father") {
            $memberGender.val("male");
        }

        filterFamilySalutationByGender($salutation, relationValue, ($memberGender.val() || "").toString());
    });

    updateFamilySummaryCounts();
}

function familyMemberRowTemplate(index) {
    var prefix = "family_members[" + index + "]";
    var deleteLabel = t("delete", kh("Delete", "áž›áž»áž”"));
    var yesLabel = t("yes", kh("Yes", "áž”áž¶áž‘/áž…áž¶ážŸ"));
    var noLabel = t("no", kh("No", "áž‘áŸ"));

    var deceasedLabels = familyDeceasedLabels();
    var aliveLabel = deceasedLabels.alive;
    var deceasedLabel = deceasedLabels.deceased;

    return '<tr class="family-member-row">' +
        "<td>" + familyRelationOptionsHtml(prefix) + "</td>" +
        "<td>" + familySalutationOptionsHtml(prefix) + "</td>" +
        '<td><input type="text" name="' + prefix + '[last_name_km]" class="form-control"></td>' +
        '<td><input type="text" name="' + prefix + '[first_name_km]" class="form-control"></td>' +
        '<td><input type="text" name="' + prefix + '[last_name_latin]" class="form-control"></td>' +
        '<td><input type="text" name="' + prefix + '[first_name_latin]" class="form-control"></td>' +
        "<td>" + familyGenderOptionsHtml(prefix) + "</td>" +
        '<td><input type="text" name="' + prefix + '[nationality]" class="form-control"></td>' +
        '<td><input type="text" name="' + prefix + '[ethnicity]" class="form-control"></td>' +
        '<td><input type="text" name="' + prefix + '[occupation]" class="form-control"></td>' +
        '<td><input type="date" name="' + prefix + '[date_of_birth]" class="form-control"></td>' +
        "<td>" + familyBirthSelectHtml(prefix, "present_address_state", "family-birth-province", "province") + "</td>" +
        "<td>" + familyBirthSelectHtml(prefix, "present_address_city", "family-birth-district", "district") + "</td>" +
        "<td>" + familyBirthSelectHtml(prefix, "present_address_commune", "family-birth-commune", "commune") + "</td>" +
        "<td>" + familyBirthSelectHtml(prefix, "present_address_village", "family-birth-village", "village") + "</td>" +
        '<td><input type="text" name="' + prefix + '[phone]" class="form-control"></td>' +
        '<td><select name="' + prefix + '[is_deceased]" class="form-select"><option value="0">' + aliveLabel + '</option><option value="1">' + deceasedLabel + "</option></select></td>" +
        '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + deleteLabel + "</button></td>" +
        "</tr>";
}

function educationDegreeLevelOptionsHtml(prefix) {
    var templateNode = document.getElementById("education-degree-level-options-template");
    var optionsHtml = templateNode ? templateNode.innerHTML.trim() : '<option value="">Select degree level</option>';
    return '<select name="' + prefix + '[degree_level]" class="form-select">' + optionsHtml + '</select>';
}
function rowTemplate(repeater, index) {
    if (repeater === "pay_grade_histories") {
        return "<tr>" +
            '<td><input type="date" name="pay_grade_histories[' + index + '][start_date]" class="form-control"></td>' +
            '<td><input type="date" name="pay_grade_histories[' + index + '][end_date]" class="form-control"></td>' +
            '<td><select name="pay_grade_histories[' + index + '][status]" class="form-select"><option value="active">' + kh("Active", "ážŸáž€áž˜áŸ’áž˜") + '</option><option value="inactive">' + kh("Inactive", "áž¢ážŸáž€áž˜áŸ’áž˜") + "</option></select></td>" +
            '<td><input type="text" name="pay_grade_histories[' + index + '][note]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "work_histories") {
        return "<tr>" +
            '<td><input type="text" name="work_histories[' + index + '][work_status_name]" class="form-control"></td>' +
            '<td><input type="date" name="work_histories[' + index + '][start_date]" class="form-control"></td>' +
            '<td><input type="text" name="work_histories[' + index + '][document_reference]" class="form-control"></td>' +
            '<td><input type="date" name="work_histories[' + index + '][document_date]" class="form-control"></td>' +
            '<td><input type="text" name="work_histories[' + index + '][note]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "incentives") {
        return "<tr>" +
            '<td><input type="date" name="incentives[' + index + '][incentive_date]" class="form-control"></td>' +
            '<td><input type="text" name="incentives[' + index + '][hierarchy_level]" class="form-control"></td>' +
            '<td><input type="text" name="incentives[' + index + '][nationality_type]" class="form-control"></td>' +
            '<td><input type="text" name="incentives[' + index + '][incentive_type]" class="form-control"></td>' +
            '<td><input type="text" name="incentives[' + index + '][incentive_class]" class="form-control"></td>' +
            '<td><input type="text" name="incentives[' + index + '][reason]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "family_members") {
        return familyMemberRowTemplate(index);
    }

    if (repeater === "family_attachments") {
        return "<tr>" +
            '<td><input type="text" name="family_attachments[' + index + '][title]" class="form-control"></td>' +
            '<td><input type="file" name="family_attachments[' + index + '][file]" class="form-control"></td>' +
            '<td><input type="date" name="family_attachments[' + index + '][expiry_date]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "education_histories") {
        return "<tr>" +
            '<td><input type="text" name="education_histories[' + index + '][institution_name]" class="form-control"></td>' +
            '<td><input type="number" name="education_histories[' + index + '][start_date]" class="form-control gov-year-input" min="1900" max="2100" step="1" placeholder="' + kh("e.g. 2020", "ឧ. ២០២០") + '"></td>' +
            '<td><input type="number" name="education_histories[' + index + '][end_date]" class="form-control gov-year-input" min="1900" max="2100" step="1" placeholder="' + kh("e.g. 2020", "ឧ. ២០២០") + '"></td>' +
            "<td>" + educationDegreeLevelOptionsHtml("education_histories[" + index + "]") + "</td>" +
            '<td><input type="text" name="education_histories[' + index + '][major_subject]" class="form-control"></td>' +
            '<td><input type="text" name="education_histories[' + index + '][note]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }
    if (repeater === "foreign_languages") {
        return "<tr>" +
            '<td><input type="text" name="foreign_languages[' + index + '][language_name]" class="form-control"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][speaking_level]" class="form-control" placeholder="A/B/C"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][reading_level]" class="form-control" placeholder="A/B/C"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][writing_level]" class="form-control" placeholder="A/B/C"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][institution_name]" class="form-control"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][start_date]" class="form-control" placeholder="DD/MM/YYYY / ' + kh("e.g. 2020", "ឧ. ២០២០") + '"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][end_date]" class="form-control" placeholder="DD/MM/YYYY / ' + kh("e.g. 2020", "ឧ. ២០២០") + '"></td>' +
            '<td><input type="text" name="foreign_languages[' + index + '][result]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "លុប")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "bank_accounts") {
        return "<tr>" +
            '<td><input type="text" name="bank_accounts[' + index + '][account_name]" class="form-control"></td>' +
            '<td><input type="text" name="bank_accounts[' + index + '][account_number]" class="form-control"></td>' +
            '<td><input type="text" name="bank_accounts[' + index + '][bank_name]" class="form-control"></td>' +
            '<td><input type="file" name="bank_accounts[' + index + '][attachment]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "bank_attachments") {
        return "<tr>" +
            '<td><input type="text" name="bank_attachments[' + index + '][title]" class="form-control"></td>' +
            '<td><input type="file" name="bank_attachments[' + index + '][file]" class="form-control"></td>' +
            '<td><input type="date" name="bank_attachments[' + index + '][expiry_date]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    if (repeater === "vaccination_records") {
        return "<tr>" +
            '<td><input type="text" name="vaccination_records[' + index + '][vaccine_name]" class="form-control"></td>' +
            '<td><input type="text" name="vaccination_records[' + index + '][vaccine_protection]" class="form-control"></td>' +
            '<td><input type="date" name="vaccination_records[' + index + '][vaccination_date]" class="form-control"></td>' +
            '<td><input type="text" name="vaccination_records[' + index + '][vaccination_place]" class="form-control"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger repeater-remove">' + t("delete", kh("Delete", "áž›áž»áž”")) + "</button></td>" +
            "</tr>";
    }

    return "";
}

function reindexRepeater(table, repeater) {
    $(table).find("tbody tr").each(function (rowIndex) {
        $(this).find("input, select, textarea").each(function () {
            var name = $(this).attr("name");
            if (!name) {
                return;
            }
            var re = new RegExp(repeater + "\\[\\d+\\]");
            $(this).attr("name", name.replace(re, repeater + "[" + rowIndex + "]"));
        });
    });
}

function updateAgeDisplay() {
    var dateInput = $("#date_of_birth");
    var ageInput = $("#employee-age-display");
    if (!dateInput.length || !ageInput.length) {
        return;
    }

    var value = dateInput.val();
    if (!value) {
        ageInput.val("");
        return;
    }

    var dob = parseIsoDateInput(value);
    if (!dob) {
        ageInput.val("");
        return;
    }

    var now = new Date();
    var years = now.getFullYear() - dob.getFullYear();
    var m = now.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) {
        years--;
    }

    ageInput.val((years >= 0 ? years : 0) + (isKhmerLocaleUi() ? " áž†áŸ’áž“áž¶áŸ†" : " years"));
}

function parseIsoDateInput(value) {
    var text = (value || "").toString().trim();
    if (!text) {
        return null;
    }

    var m = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) {
        return null;
    }

    var d = new Date(m[1] + "-" + m[2] + "-" + m[3] + "T00:00:00");
    return isNaN(d.getTime()) ? null : d;
}

function validateEmploymentDateSequence() {
    var dob = parseIsoDateInput($("#date_of_birth").val());
    var joining = parseIsoDateInput($("#joining_date").val());
    var service = parseIsoDateInput($("#service_start_date").val());

    if (!dob) {
        return true;
    }

    var today = new Date();
    today.setHours(0, 0, 0, 0);
    if (dob > today) {
        alert("ថ្ងៃខែឆ្នាំកំណើត មិនអាចលើសថ្ងៃបច្ចុប្បន្នបានទេ។");
        return false;
    }

    if (joining && dob > joining) {
        alert("ថ្ងៃចូលធ្វើការ ត្រូវធំជាង ឬស្មើថ្ងៃខែឆ្នាំកំណើត។");
        return false;
    }

    if (service && dob > service) {
        alert("ថ្ងៃចាប់ផ្តើមសេវា ត្រូវធំជាង ឬស្មើថ្ងៃខែឆ្នាំកំណើត។");
        return false;
    }

    if (joining && service && joining > service) {
        alert("ថ្ងៃចាប់ផ្តើមសេវា មិនអាចតូចជាងថ្ងៃចូលធ្វើការបានទេ។");
        return false;
    }

    return true;
}

$(document).on("click", ".repeater-add", function () {
    var target = $(this).data("target");
    var repeater = $(this).data("repeater");
    var table = $(target);
    if (!table.length || !repeater) {
        return;
    }

    var index = table.find("tbody tr").length;
    var row = rowTemplate(repeater, index);
    if (!row) {
        return;
    }

    table.find("tbody").append(row);
    if (repeater === "family_members") {
        applyFamilySectionRules();
        initFamilyBirthCascades();
    }
});

$(document).on("click", ".repeater-remove", function () {
    var row = $(this).closest("tr");
    var table = row.closest("table");
    if (!table.length) {
        return;
    }

    if (table.find("tbody tr").length === 1) {
        row.find("input[type=text], input[type=date], input[type=email], input[type=number], textarea").val("");
        row.find("select").prop("selectedIndex", 0);
        if (table.attr("id") === "family-members-table") {
            applyFamilySectionRules();
            initFamilyBirthCascades();
        }
        return;
    }

    row.remove();
    var repeater = table.closest(".table-responsive").find(".repeater-add").data("repeater");
    if (repeater) {
        reindexRepeater(table, repeater);
        if (repeater === "family_members") {
            applyFamilySectionRules();
            initFamilyBirthCascades();
        }
    }
});

$(document).ready(function () {
    $("#email").on("change", function () {
        $("#employee-email").val($(this).val());
    });

    toggleCivilServantWorkflowFields();
    $("#employee_type_id").on("change", toggleCivilServantWorkflowFields);
    $("#is_full_right_officer").on("change", function () {
        toggleFullRightOfficerFields();
        updateCivilServantPhasePreview();
    });
    $("#service_start_date, #joining_date").on("change", updateCivilServantPhasePreview);

    toggleCivilServiceCardFields();
    $(document).on("change", "input[name='work_permit']", toggleCivilServiceCardFields);

    toggleMedicalDisabilityFields();
    $(document).on("change", "#medical_is_disable", toggleMedicalDisabilityFields);

    toggleEthnicMinorityFields();
    $(document).on("change", "#is_ethnic_minority, #ethnic_minority_name", toggleEthnicMinorityFields);

    updateWidowedMaritalLabel();
    applyFamilySectionRules();
    initFamilyBirthCascades();
    $(document).on("change", "#marital_status_id, #gender_id", function () {
        updateWidowedMaritalLabel();
        applyFamilySectionRules();
    });
    $(document).on("change", "#family-members-table .family-relation-type, #family-members-table .family-member-gender", function () {
        applyFamilySectionRules();
    });

    updateAgeDisplay();
    $("#date_of_birth").on("change", updateAgeDisplay);

    $("form.f1").on("submit", function (e) {
        if (!validateEmploymentDateSequence()) {
            e.preventDefault();
        }
    });
});
