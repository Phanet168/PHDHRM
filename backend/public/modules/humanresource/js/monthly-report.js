$("#searchreset").click(function () {
    if ($("#workplace_id").length) {
        $("#workplace_id").val("").trigger("change");
    }
    if ($("#department_id").length) {
        $("#department_id").val("").trigger("change");
    }
    $("#employee_id").val("").trigger("change");
    $("#year").val("").trigger("change");
    $("#month").val("").trigger("change");
    var redirectUrl = $("#monthlyUrl").val();
    window.location.href = redirectUrl;
});

$("#filter").click(function () {
    var workplace_id = $("#workplace_id").length ? $("#workplace_id").val() : $("#department_id").val();
    var employee_id = $("#employee_id").val();
    var year = $("#year").val();
    var month = $("#month").val();
    var url = $("#url").val();
    if (!workplace_id && !employee_id && !year && !month) {
        toastr.error("Please select workplace, employee, year and month");
        return;
    } else if (!year) {
        toastr.error("Please select year");
        return;
    } else if (!month) {
        toastr.error("Please select month");
        return;
    } else if (!workplace_id) {
        toastr.error("Please select workplace");
        return;
    } else if (!employee_id) {
        toastr.error("Please select employee");
        return;
    }

    axios
        .get(url, {
            params: {
                workplace_id,
                department_id: workplace_id,
                employee_id,
                year,
                month,
            },
        })
        .then(function (response) {
            $("#report-result").html(response.data);
        })
        .catch(function (error) {
            console.log(error);
        });
});

$("#workplace_id, #department_id").change(function (e) {
    e.preventDefault();

    var lang_all = $("#lang_all").val();

    var workplace_id = $("#workplace_id").length ? $("#workplace_id").val() : $("#department_id").val();
    var url = $("#get_employees_department").val();
    if (workplace_id > 0 && workplace_id) {
        axios
            .get(url, {
                params: {
                    id: workplace_id,
                    workplace_id: workplace_id,
                },
            })
            .then(function (response) {
                var employees = response.data;
                var html = '<option value="0">' + lang_all + "</option>";
                html += employees
                    .map(
                        (employee) =>
                            `<option value="${employee.id}">
                                ${employee?.first_name || ""} 
                                ${employee?.middle_name || ""} 
                                ${employee?.last_name || ""}
                            </option>`
                    )
                    .join("");
                $("#employee_id").html(html);
            })
            .catch(function (error) {
                console.log(error);
            });
    } else {
        $("#employee_id").html('<option value="0">' + lang_all + "</option>");
    }
});
