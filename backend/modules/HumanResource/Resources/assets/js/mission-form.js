(function () {
    function initEmployeeSearchSelect() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 !== 'function') {
            return;
        }
        jQuery('.mission-employee-select').each(function () {
            var $select = jQuery(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            $select.select2({
                width: '100%',
                placeholder: 'ស្វែងរក ហើយជ្រើសរើសមន្ត្រីម្នាក់ៗ',
                allowClear: true,
                closeOnSelect: false,
            });
        });
    }

    function initLunarDateAutoFill() {
        var issuedOn = document.querySelector('input[name="order_details[issued_on]"]');
        var startDate = document.querySelector('input[name="start_date"]');
        var lunarField = document.querySelector('input[name="order_details[lunar_date]"]');
        var urlInput = document.querySelector('meta[name="mission-lunar-date-url"]');
        if (!lunarField || !urlInput) {
            return;
        }
        var baseUrl = urlInput.getAttribute('content');

        lunarField.addEventListener('input', function () {
            lunarField.dataset.touched = '1';
        });

        function fillFromDate(dateValue) {
            if (!dateValue || lunarField.dataset.touched === '1') {
                return;
            }
            fetch(baseUrl + '?date=' + encodeURIComponent(dateValue), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (json) {
                    var text = json && json.response && json.response.data ? json.response.data.lunar_date : '';
                    if (text && lunarField.dataset.touched !== '1') {
                        lunarField.value = text;
                    }
                })
                .catch(function () {});
        }

        function currentSolarDate() {
            if (issuedOn && issuedOn.value) {
                return issuedOn.value;
            }
            if (startDate && startDate.value) {
                return startDate.value;
            }
            return '';
        }

        if (issuedOn) {
            issuedOn.addEventListener('change', function () { fillFromDate(issuedOn.value); });
        }
        if (startDate) {
            startDate.addEventListener('change', function () {
                if (!issuedOn || !issuedOn.value) {
                    fillFromDate(startDate.value);
                }
            });
        }

        var initial = currentSolarDate();
        if (initial && !lunarField.value) {
            fillFromDate(initial);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initEmployeeSearchSelect();
        initLunarDateAutoFill();
    });
})();
