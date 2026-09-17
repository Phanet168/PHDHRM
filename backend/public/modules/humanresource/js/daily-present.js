$('#searchreset').click(function() {
    if ($('#workplace_id').length) {
        $('#workplace_id').val('').trigger('change');
    }
    if ($('#department_id').length) {
        $('#department_id').val('').trigger('change');
    }
    $('#date').val('').trigger('change');
    var table = $("#daily-present-report-table");
    if (!table.length) {
        return;
    }
    table.on("preXhr.dt", function(e, settings, data) {
        data.workplace_id = "";
        data.department_id = "";
        data.date = "";

        if ($("#workplace_id").length) {
            $("#workplace_id").select2({
                placeholder: "All Workplaces",
            });
        } else {
            $("#department_id").select2({
                placeholder: "All Departments",
            });
        }
    });
    table.DataTable().ajax.reload();
});

$('#filter').click(function() {
    var workplace_id = $('#workplace_id').length ? $('#workplace_id').val() : $('#department_id').val();
    var date = $('#date').val();
    // validate workplace & date required
    if (!workplace_id && !date) {
        toastr.error('Please select workplace and date');
        return;
    } else if (workplace_id && !date) {
        toastr.error('Please select date');
        return;
    } else if (!workplace_id && date) {
        toastr.error('Please select workplace');
        return;
    }
    var table = $("#daily-present-report-table");
    if (!table.length) {
        return;
    }
    table.on("preXhr.dt", function(e, settings, data) {
        data.workplace_id = workplace_id;
        data.department_id = workplace_id;
        data.date = $("#date").val();
    });
    table.DataTable().ajax.reload();
});
