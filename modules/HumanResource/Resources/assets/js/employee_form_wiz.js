"use strict"; // Start of use strict
function scroll_to_class(element_class, removed_height) {
    var scroll_to = $(element_class).offset().top - removed_height;
    if ($(window).scrollTop() !== scroll_to) {
        $("html, body").stop().animate({ scrollTop: scroll_to }, 0);
    }
}

function bar_progress(progress_line_object, direction) {
    var number_of_steps = progress_line_object.data("number-of-steps");
    var now_value = progress_line_object.data("now-value");
    var new_value = 0;
    if (direction === "right") {
        new_value = now_value + 100 / number_of_steps;
    } else if (direction === "left") {
        new_value = now_value - 100 / number_of_steps;
    }
    progress_line_object
        .attr("style", "width: " + new_value + "%;")
        .data("now-value", new_value);
}

function shouldValidateRequiredField($field) {
    return $field.is(":enabled") && $field.is(":visible") && !$field.hasClass("skip-required");
}

function validationMessage() {
    try {
        var translated = localize("please_fill_all_required_fields");
        if (translated && translated !== "please_fill_all_required_fields") {
            return translated;
        }
    } catch (e) {
        // ignore translation failures
    }
    return "Please fill all required fields.";
}

function markRequiredLabels($form) {
    if (!$form || !$form.length) {
        return;
    }

    $form.find("input.required-field, select.required-field, textarea.required-field, input[required], select[required], textarea[required]").each(function () {
        var $field = $(this);
        var fieldId = ($field.attr("id") || "").trim();
        var $label = $();

        if (fieldId) {
            $label = $form.find('label[for="' + fieldId + '"]').first();
        }

        if (!$label.length) {
            $label = $field.closest(".form-group, .cust_border, .row").find("label").first();
        }

        if (!$label.length) {
            return;
        }

        var $star = $label.find(".required-star").first();
        if (!$star.length) {
            $star = $label
                .find("span.text-danger")
                .filter(function () {
                    return $.trim($(this).text()) === "*";
                })
                .first();
            if ($star.length) {
                $star.addClass("required-star");
            }
        }

        if (!$star.length) {
            $star = $('<span class="text-danger required-star">*</span>');
            $label.prepend($star).prepend(" ");
        } else {
            $star.detach();
            $label.prepend($star).prepend(" ");
        }

        $star.show();
    });
}

function fieldHasValue($form, $field) {
    var type = (($field.attr("type") || "") + "").toLowerCase();

    if (type === "radio" || type === "checkbox") {
        var name = $field.attr("name");
        if (!name) {
            return $field.is(":checked");
        }
        return $form.find('[name="' + name + '"]:checked').length > 0;
    }

    if ($field.is("select") && $field.prop("multiple")) {
        var values = $field.val() || [];
        return Array.isArray(values) ? values.length > 0 : false;
    }

    var value = ($field.val() || "") + "";
    return $.trim(value) !== "";
}

function toggleRequiredStarForField($form, $field, $star) {
    if (!$star || !$star.length) {
        return;
    }

    $star.show();
}

function validateDmyFieldValue(value) {
    var text = (value || "").toString().trim();
    if (text === "") {
        return true;
    }

    var match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) {
        return false;
    }

    var dd = parseInt(match[1], 10);
    var mm = parseInt(match[2], 10);
    var yyyy = parseInt(match[3], 10);
    if (yyyy < 1900 || yyyy > 2100 || mm < 1 || mm > 12 || dd < 1 || dd > 31) {
        return false;
    }

    var test = new Date(yyyy, mm - 1, dd);
    return test.getFullYear() === yyyy && test.getMonth() === (mm - 1) && test.getDate() === dd;
}

function validateFieldsetForNextStep($form, $fieldset) {
    var isValid = true;

    $fieldset
        .find("input.required-field, select.required-field, textarea.required-field, input[required], select[required], textarea[required]")
        .each(function () {
            var $field = $(this);
            if (!shouldValidateRequiredField($field)) {
                $field.removeClass("input-error");
                return;
            }

            if (!fieldHasValue($form, $field)) {
                $field.addClass("input-error");
                isValid = false;
            } else {
                $field.removeClass("input-error");
            }
        });

    $fieldset.find("input.hard-ddmmyyyy-date:enabled:visible").each(function () {
        var $field = $(this);
        if (!validateDmyFieldValue($field.val())) {
            $field.addClass("input-error");
            isValid = false;
        }
    });

    if ($fieldset.find(".is-invalid:enabled:visible, .error.text-danger:visible").length > 0) {
        isValid = false;
    }

    return isValid;
}

function setWizardStepByFieldset($form, $targetFieldset) {
    if (!$form.length || !$targetFieldset.length) {
        return;
    }

    var $fieldsets = $form.find("fieldset");
    var targetIndex = $fieldsets.index($targetFieldset);
    if (targetIndex < 0) {
        return;
    }

    $fieldsets.hide();
    $targetFieldset.show();

    var $steps = $form.find(".f1-step");
    $steps.removeClass("active activated");

    $steps.each(function (idx) {
        if (idx < targetIndex) {
            $(this).addClass("activated");
        } else if (idx === targetIndex) {
            $(this).addClass("active");
        }
    });

    var $progressLine = $form.find(".f1-progress-line");
    var numberOfSteps = parseFloat($progressLine.data("number-of-steps"));
    if (!numberOfSteps || numberOfSteps <= 0) {
        return;
    }

    var newValue = ((targetIndex + 1) * 100) / numberOfSteps;
    $progressLine
        .attr("style", "width: " + newValue + "%;")
        .data("now-value", newValue);
}

function resolveFieldFromErrorElement($errorElement) {
    if (!$errorElement || !$errorElement.length) {
        return $();
    }

    if ($errorElement.is("input, select, textarea")) {
        return $errorElement;
    }

    var $fromGroup = $errorElement
        .closest(".form-group, .cust_border, .input-group, .col-lg-9, .col-md-12")
        .find("input, select, textarea")
        .filter(":enabled")
        .first();
    if ($fromGroup.length) {
        return $fromGroup;
    }

    var $fromSiblings = $errorElement
        .prevAll("input, select, textarea")
        .filter(":enabled")
        .first();
    if ($fromSiblings.length) {
        return $fromSiblings;
    }

    return $();
}

function jumpToFirstValidationError($form) {
    if (!$form.length) {
        return;
    }

    var $firstErrorControl = $form
        .find(".is-invalid, .input-error")
        .filter(":enabled")
        .first();

    var $targetField = $firstErrorControl;
    if (!$targetField.length) {
        var $firstErrorText = $form
            .find(".error.text-danger, .invalid-feedback")
            .filter(function () {
                return $.trim($(this).text()) !== "";
            })
            .first();
        $targetField = resolveFieldFromErrorElement($firstErrorText);
    }

    if (!$targetField.length) {
        return;
    }

    var fieldName = "";
    var $label = $targetField
        .closest(".form-group, .cust_border, .row")
        .find("label")
        .first();
    if ($label.length) {
        fieldName = $.trim($label.text()).replace(/\*/g, "");
    }

    if (!fieldName) {
        fieldName = ($targetField.attr("placeholder") || "").trim();
    }

    var $targetFieldset = $targetField.closest("fieldset");
    if ($targetFieldset.length) {
        setWizardStepByFieldset($form, $targetFieldset);
    }

    $(".employee-validation-focus").removeClass("employee-validation-focus");
    $targetField.addClass("employee-validation-focus");

    if ($("#employee-validation-focus-style").length === 0) {
        $("head").append(
            '<style id="employee-validation-focus-style">.employee-validation-focus{border-color:#dc3545 !important;box-shadow:0 0 0 .2rem rgba(220,53,69,.25) !important;}</style>'
        );
    }

    if (fieldName) {
        var kmPrefix = "សូមពិនិត្យកំហុសនៅប្រអប់";
        var enPrefix = "Please check this field:";
        var lang = ((document.documentElement.lang || "") + "").toLowerCase();
        var msg = (lang.indexOf("km") === 0 ? kmPrefix : enPrefix) + " " + fieldName;

        if (typeof toastr !== "undefined") {
            toastr.error(msg);
        }
    }

    scroll_to_class($targetField, 120);
    $targetField.trigger("focus");
}

jQuery(document).ready(function () {
    // Form

    $(".f1 fieldset:first").fadeIn("slow");

    $(".f1").each(function () {
        var $form = $(this);
        markRequiredLabels($form);
        setTimeout(function () {
            jumpToFirstValidationError($form);
        }, 120);
    });

    $(".f1 .required-field").on("focus", function () {
        $(this).removeClass("input-error");
    });

    // next step
    $(".f1 .btn-next").on("click", function () {
        var $form = $(this).parents(".f1");
        var parent_fieldset = $(this).parents("fieldset");
        var next_step = validateFieldsetForNextStep($form, parent_fieldset);
        // navigation steps / progress steps
        var current_active_step = $(this)
            .parents(".f1")
            .find(".f1-step.active");
        var progress_line = $(this).parents(".f1").find(".f1-progress-line");

        if (next_step) {
            parent_fieldset.fadeOut(400, function () {
                // change icons
                current_active_step
                    .removeClass("active")
                    .addClass("activated")
                    .next()
                    .addClass("active");
                // progress bar
                bar_progress(progress_line, "right");
                // show next step
                $(this).next().fadeIn();
                // scroll window to beginning of the form
                scroll_to_class($(".f1"), 20);
            });
        } else {
            jumpToFirstValidationError($form);
            if (typeof toastr !== "undefined") {
                toastr.error(validationMessage());
            }
        }
    });

    // previous step
    $(".f1 .btn-previous").on("click", function () {
        // navigation steps / progress steps
        var current_active_step = $(this)
            .parents(".f1")
            .find(".f1-step.active");
        var progress_line = $(this).parents(".f1").find(".f1-progress-line");

        $(this)
            .parents("fieldset")
            .fadeOut(400, function () {
                // change icons
                current_active_step
                    .removeClass("active")
                    .prev()
                    .removeClass("activated")
                    .addClass("active");
                // progress bar
                bar_progress(progress_line, "left");
                // show previous step
                $(this).prev().fadeIn();
                // scroll window to beginning of the form
                scroll_to_class($(".f1"), 20);
            });
    });

    // submit
    $(".f1").on("submit", function (e) {
        // fields validation
        var hasError = false;
        var $firstError = null;

        $(this)
            .find(".required-field")
            .each(function () {
                var $field = $(this);
                if (!shouldValidateRequiredField($field)) {
                    $field.removeClass("input-error");
                    return;
                }

                if ($field.val() === "") {
                    hasError = true;
                    $field.addClass("input-error");
                    if (!$firstError) {
                        $firstError = $field;
                    }
                } else {
                    $field.removeClass("input-error");
                }
            });

        if (hasError) {
            e.preventDefault();
            if (typeof toastr !== "undefined") {
                toastr.error(validationMessage());
            }
            if ($firstError) {
                scroll_to_class($firstError, 120);
                $firstError.trigger("focus");
            }
        }
        // fields validation
    });

    //show and hide disability input
    $(".disabilities_desc").parent().closest(".cust_border").hide();
    if ($("input[type=radio][name=is_disable]").val() == 1) {
        $(".disabilities_desc").parent().closest(".cust_border").show();
    } else {
        $(".disabilities_desc").parent().closest(".cust_border").hide();
    }
    $("input[type=radio][name=is_disable]").change(function () {
        if (this.value == 1) {
            $(".disabilities_desc").parent().closest(".cust_border").show();
        } else if (this.value == 0) {
            $(".disabilities_desc").parent().closest(".cust_border").hide();
        }
    });

    var duty_type = $("#duty_type").find(":selected").val();
    if (duty_type == 3) {
        $(".contractual").parent().parent().show();
    } else {
        $(".contractual").parent().parent().hide();
    }
    $("#duty_type").on("change", function () {
        if (this.value == 3) {
            $(".contractual").parent().parent().show();
        } else {
            $("input.contractual").val("");
            $(".contractual").parent().parent().hide();
        }
    });
});
