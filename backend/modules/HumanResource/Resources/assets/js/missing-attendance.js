var SESSIONS = ["morning", "afternoon", "night"];

$(document).on("change", ".row-type", function () {
    var $row = $(this).closest("tr");
    var type = $(this).val();
    // .shift-select is wrapped in .shift-select-wrap so toggling it also
    // hides/shows the Select2 widget rendered next to the (now-hidden)
    // native <select> -- toggling the <select> itself wouldn't touch that.
    $row.find(".shift-select-wrap").toggle(type === "shift");
    $row.find(".session-in, .session-out").prop("disabled", type !== "in_out");
    if (type !== "in_out") {
        $row.find(".session-in, .session-out").removeClass("is-invalid");
    }
});

$(document).on("click", "#submit", function (e) {
    e.preventDefault();
    var data = checkMissingEmployees();
    if (data.employee_id.length > 0) {
        validateCheckedValue();
        if ($(".is-invalid").length == 0) {
            $.ajax({
                url: $("#missingAttnStore").val(),
                type: "POST",
                data: $.extend({ date: $("#date").val() }, data),
                success: function (response) {
                    if (response.status == 200) {
                        toastr.success(response.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function (xhr) {
                    var message =
                        (xhr.responseJSON && xhr.responseJSON.message) ||
                        (xhr.responseJSON &&
                            xhr.responseJSON.errors &&
                            Object.values(xhr.responseJSON.errors)[0][0]) ||
                        localize("something_went_wrong");
                    toastr.error(message);
                },
            });
        } else {
            toastr.error(localize("please_fill_all_required_fields"));
        }
    } else {
        toastr.error(localize("please_select_employee"));
    }
});

// Check All
$("#checkAll").click(function () {
    if ($(this).is(":checked")) {
        $(".checkSingle").prop("checked", true);
    } else {
        $(".checkSingle").prop("checked", false);
    }
});

function validateCheckedValue() {
    $(".checkSingle").each(function () {
        if (!$(this).is(":checked")) {
            return;
        }
        var $row = $(this).closest("tr");
        var type = $row.find(".row-type").val();
        if (type === "in_out") {
            var anyComplete = false;
            SESSIONS.forEach(function (session) {
                var $in = $row.find('.session-in[data-session="' + session + '"]');
                var $out = $row.find('.session-out[data-session="' + session + '"]');
                var incomplete = ($in.val() && !$out.val()) || (!$in.val() && $out.val());
                $in.toggleClass("is-invalid", incomplete);
                $out.toggleClass("is-invalid", incomplete);
                if ($in.val() && $out.val()) {
                    anyComplete = true;
                }
            });
            if (!anyComplete) {
                SESSIONS.forEach(function (session) {
                    $row.find('.session-in[data-session="' + session + '"]').addClass("is-invalid");
                });
            }
        } else if (type === "shift") {
            var shiftId = $row.find(".shift-select").val();
            $row.find(".shift-select").toggleClass("is-invalid", !shiftId);
        }
    });
}

function checkMissingEmployees() {
    var employee_id = [],
        type = [],
        shift_id = [];
    var sessionData = {};
    SESSIONS.forEach(function (session) {
        sessionData[session + "_in"] = [];
        sessionData[session + "_out"] = [];
    });
    $(".checkSingle:checked").each(function () {
        var $row = $(this).closest("tr");
        employee_id.push($(this).val());
        type.push($row.find(".row-type").val());
        shift_id.push($row.find(".shift-select").val());
        SESSIONS.forEach(function (session) {
            var $in = $row.find('.session-in[data-session="' + session + '"]');
            var $out = $row.find('.session-out[data-session="' + session + '"]');
            // A row without this session (e.g. no "night" for non-duty staff)
            // has no matching input -- push "" rather than undefined so every
            // per-employee array stays the same length and index-aligned.
            sessionData[session + "_in"].push($in.length ? $in.val() : "");
            sessionData[session + "_out"].push($out.length ? $out.val() : "");
        });
    });
    return $.extend({ employee_id: employee_id, type: type, shift_id: shift_id }, sessionData);
}
