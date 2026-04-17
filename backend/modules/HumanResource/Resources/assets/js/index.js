(function () {
    "use strict";

    var canvas = document.getElementById("todays_attendance");
    if (!canvas || typeof Chart === "undefined") {
        return;
    }

    var presentInput = document.getElementById("today_attenedence");
    var absentInput = document.getElementById("today_absence");
    var presentLabelInput = document.getElementById("today_label_present");
    var absentLabelInput = document.getElementById("today_label_absent");

    var present = parseInt((presentInput && presentInput.value) || "0", 10);
    var absent = parseInt((absentInput && absentInput.value) || "0", 10);

    if (!Number.isFinite(present)) {
        present = 0;
    }
    if (!Number.isFinite(absent)) {
        absent = 0;
    }

    new Chart(canvas, {
        type: "doughnut",
        data: {
            labels: [
                (presentLabelInput && presentLabelInput.value) || "Present",
                (absentLabelInput && absentLabelInput.value) || "Absent",
            ],
            datasets: [
                {
                    data: [present, absent],
                    backgroundColor: ["#198754", "#dc3545"],
                    hoverBackgroundColor: ["#157347", "#bb2d3b"],
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "bottom",
                },
            },
        },
    });
})();
