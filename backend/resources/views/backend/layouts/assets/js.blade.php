<script src="{{ asset('backend/assets/plugins/jQuery/jquery.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/jquery-ui-1.13.2/jquery-ui.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/jquery-ui-timepicker-addon.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/metisMenu/metisMenu.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/perfect-scrollbar/dist/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('backend/assets/common/js/marks-read-notification.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/jstree.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/tableHeadFixer.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/wickedpicker.min.js') }}"></script>

<script src="{{ asset('backend/assets/plugins/icheck/icheck.min.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/pages/icheck.active.js') }}"></script>

<script src="{{ asset('backend/assets/plugins/jstree.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/tableHeadFixer.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/validator/jquery.validate.js') }}"></script>
<script src="{{ asset('backend/assets/jquery.smartmenus.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/sidebar.js') }}"></script>

<!-- Datatable -->
<script src="{{ asset('backend/assets/plugins/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/dataTables.bootstrap.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/responsive.bootstrap.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/buttons.bootstrap.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/jszip.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/pdfmake.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/vfs_fonts.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/buttons.html5.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/buttons.print.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/datatables/dataTables.fixedHeader.min.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/data-bootstrap.active.js') }}"></script>
<!-- Datatable -->


<!-- Toastr -->
<script src="{{ asset('backend/assets/plugins/toastr/toastr.min.js') }}"></script>
{!! Toastr::message() !!}
<!-- Toastr -->

<script src="{{ asset('backend/assets/navActive.js') }}"></script>

<script src="{{ asset('backend/assets/dist/js/pages/forms-basic.active.js') }}"></script>

<!-- Dropzone -->
<script src="{{ asset('backend/assets/plugins/dropzone-5.7.0/dropzone.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/dropzone-5.7.0/dropzone.active.js') }}"></script>
<!-- Dropzone -->

<!--Start Date Time Picker-->
<script src="{{ asset('backend/assets/plugins/datetimepicker/build/jquery.datetimepicker.full.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/daterangepicker/daterangepicker.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/daterangepicker/daterangepicker.active.js') }}"></script>
<!--End Date Time Picker-->

<!--Select 2-->
<script src="{{ asset('backend/assets/plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/jquery.sumoselect/jquery.sumoselect.min.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/pages/demo.select2.js') }}"></script>
<!--Select 2-->

<!-- Start Sweet Alert -->
<script src="{{ asset('backend/assets/plugins/sweetalert/sweetalert2@10.js') }}"></script>
<script src="{{ asset('backend/assets/custom_sweetalert.js') }}"></script>
<!-- End Sweet Alert -->

<!-- Start Bootstrap Toggle -->
<script src="{{ asset('backend/assets/plugins/bootstrap-toggle/js/bootstrap-toggle.min.js') }}"></script>
<!-- End Bootstrap Toggle -->

<script src="{{ asset('backend/assets/plugins/axios.min.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/custom.js') }}?v={{ @filemtime(public_path('backend/assets/dist/js/custom.js')) }}"></script>
<script src="{{ asset('backend/assets/dist/js/jsPDF.js') }}"></script>

<script src="{{ asset('backend/assets/dist/js/user-profile-image.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/user-password-change.js') }}"></script>
<script src="{{ asset('backend/assets/dist/js/print.min.js') }}"></script>

<!-- am5 ready function -->
<script src="{{ asset('backend/assets/plugins/chartJs/Chart.min.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/amcharts5/index.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/amcharts5/percent.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/amcharts5/themes/Animated.js') }}"></script>
<script src="{{ asset('backend/assets/plugins/amcharts5/xy.js') }}"></script>

<script>
	(function($) {
		"use strict";

		function normalizeDateTimeValue(value) {
			if (!value) {
				return value;
			}

			// HTML datetime-local uses "T", plugin expects a space.
			return value.replace('T', ' ');
		}

		function toUiDate(value) {
			if (!value) {
				return value;
			}

			var textValue = String(value).trim();
			var isoMatch = textValue.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s.*)?$/);
			if (isoMatch) {
				return isoMatch[3] + '-' + isoMatch[2] + '-' + isoMatch[1];
			}

			if (!/^\d{4}-\d{2}-\d{2}$/.test(textValue)) {
				var legacyMatch = textValue.match(/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/);
				if (!legacyMatch) {
					return textValue;
				}

				var monthKey = legacyMatch[2].toLowerCase();
				if (!EN_MONTH_MAP[monthKey]) {
					return textValue;
				}

				return String(parseInt(legacyMatch[1], 10)).padStart(2, '0') + '-' + EN_MONTH_MAP[monthKey] + '-' + legacyMatch[3];
			}

			var parts = textValue.split('-');
			return parts[2] + '-' + parts[1] + '-' + parts[0];
		}

		function toUiDateTime(value) {
			if (!value) {
				return value;
			}

			var normalized = normalizeDateTimeValue(value);
			var match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})\s(\d{2}:\d{2})/);
			if (!match) {
				return normalized;
			}

			return match[3] + '-' + match[2] + '-' + match[1] + ' ' + match[4];
		}

		function normalizeDateInput(value) {
			if (value === null || value === undefined) {
				return '';
			}

			return String(value).trim().replace(/[-.]/g, '/').replace(/\s+/g, '');
		}

		function parseUiDate(value) {
			var normalized = normalizeDateInput(value);
			if (normalized === '') {
				return null;
			}

			var match = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
			if (!match) {
				var ymdMatch = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
				if (ymdMatch) {
					normalized = String(parseInt(ymdMatch[3], 10)) + '/' + String(parseInt(ymdMatch[2], 10)) + '/' + ymdMatch[1];
					match = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
				}
			}

			if (!match) {
				var textMonthMatch = normalized.match(/^(\d{1,2})\/([A-Za-z]{3})\/(\d{4})$/);
				if (textMonthMatch) {
					var monthKey = textMonthMatch[2].toLowerCase();
					if (EN_MONTH_MAP[monthKey]) {
						normalized = String(parseInt(textMonthMatch[1], 10)) + '/' + EN_MONTH_MAP[monthKey] + '/' + textMonthMatch[3];
						match = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
					}
				}
			}

			if (!match) {
				return null;
			}

			var day = parseInt(match[1], 10);
			var month = parseInt(match[2], 10);
			var year = parseInt(match[3], 10);

			if (month < 1 || month > 12 || day < 1 || day > 31) {
				return null;
			}

			var date = new Date(year, month - 1, day);
			if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
				return null;
			}

			return {
				dd: String(day).padStart(2, '0'),
				mm: String(month).padStart(2, '0'),
				yyyy: String(year)
			};
		}

		function parseUiDateTime(value) {
			if (value === null || value === undefined) {
				return null;
			}

			var normalized = String(value).trim().replace(/[-.]/g, '/').replace(/\s+/g, ' ');
			if (normalized === '') {
				return null;
			}

			var match = normalized.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s(\d{1,2}):(\d{1,2})$/);
			if (!match) {
				var ymdMatch = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s(\d{1,2}):(\d{1,2})$/);
				if (!ymdMatch) {
					return null;
				}
				match = [ymdMatch[0], ymdMatch[3], ymdMatch[2], ymdMatch[1], ymdMatch[4], ymdMatch[5]];
			}

			var datePart = parseUiDate(match[1] + '/' + match[2] + '/' + match[3]);
			if (!datePart) {
				return null;
			}

			var hour = parseInt(match[4], 10);
			var minute = parseInt(match[5], 10);
			if (hour < 0 || hour > 23 || minute < 0 || minute > 59) {
				return null;
			}

			return {
				dd: datePart.dd,
				mm: datePart.mm,
				yyyy: datePart.yyyy,
				hh: String(hour).padStart(2, '0'),
				ii: String(minute).padStart(2, '0')
			};
		}

		function toStorageDate(value) {
			if (value === null || value === undefined || String(value).trim() === '') {
				return '';
			}

			var parsed = parseUiDate(value);
			if (!parsed) {
				return null;
			}

			return parsed.yyyy + '-' + parsed.mm + '-' + parsed.dd;
		}

		function toStorageDateTime(value) {
			if (value === null || value === undefined || String(value).trim() === '') {
				return '';
			}

			var parsed = parseUiDateTime(value);
			if (!parsed) {
				return null;
			}

			return parsed.yyyy + '-' + parsed.mm + '-' + parsed.dd + ' ' + parsed.hh + ':' + parsed.ii;
		}

		var KH_MONTHS = [
			'មករា',
			'កុម្ភៈ',
			'មីនា',
			'មេសា',
			'ឧសភា',
			'មិថុនា',
			'កក្កដា',
			'សីហា',
			'កញ្ញា',
			'តុលា',
			'វិច្ឆិកា',
			'ធ្នូ'
		];

		var KH_DAY_SHORT = ['អា', 'ច', 'អ', 'ពុ', 'ព្រ', 'សុ', 'ស'];
		var KH_DAY_FULL = ['អាទិត្យ', 'ចន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'];
		var EN_MONTH_MAP = {
			jan: '01',
			feb: '02',
			mar: '03',
			apr: '04',
			may: '05',
			jun: '06',
			jul: '07',
			aug: '08',
			sep: '09',
			oct: '10',
			nov: '11',
			dec: '12'
		};

		function forceUiDateFormatOnPlugins() {
			if (typeof $.fn.datepicker === 'function' && !$.fn.datepicker.__ddmmyyyyPatched) {
				var originalDatepicker = $.fn.datepicker;
				$.fn.datepicker = function(options) {
					if (typeof options === 'string') {
						return originalDatepicker.apply(this, arguments);
					}

					var safeOptions = $.extend({}, options || {});
					safeOptions.dateFormat = 'dd-mm-yy';
					safeOptions.monthNames = KH_MONTHS;
					safeOptions.monthNamesShort = KH_MONTHS;
					safeOptions.dayNames = KH_DAY_FULL;
					safeOptions.dayNamesShort = KH_DAY_SHORT;
					safeOptions.dayNamesMin = KH_DAY_SHORT;

					return originalDatepicker.call(this, safeOptions);
				};
				$.fn.datepicker.__ddmmyyyyPatched = true;
			}

			if (typeof $.fn.datetimepicker === 'function' && !$.fn.datetimepicker.__ddmmyyyyPatched) {
				var originalDatetimepicker = $.fn.datetimepicker;
				$.fn.datetimepicker = function(options) {
					if (typeof options === 'string') {
						return originalDatetimepicker.apply(this, arguments);
					}

					var safeOptions = $.extend({}, options || {});
					safeOptions.lang = 'km';

					if (safeOptions.datepicker === false) {
						safeOptions.format = 'H:i';
					} else if (safeOptions.timepicker === false) {
						safeOptions.format = 'd-m-Y';
					} else {
						safeOptions.format = 'd-m-Y H:i';
					}

					return originalDatetimepicker.call(this, safeOptions);
				};
				$.fn.datetimepicker.__ddmmyyyyPatched = true;
			}
		}

		function initDatePickerLocale() {
			if (typeof $.datetimepicker === 'undefined' || typeof $.datetimepicker.setLocale !== 'function') {
				return;
			}

			if (typeof $.datetimepicker.i18n === 'undefined' || $.datetimepicker.i18n === null) {
				$.datetimepicker.i18n = {};
			}

			$.datetimepicker.i18n.km = {
				months: KH_MONTHS,
				dayOfWeek: KH_DAY_SHORT,
				today: 'ថ្ងៃនេះ',
				clear: 'សម្អាត'
			};

			$.datetimepicker.setLocale('km');
		}

		function initJqueryUiDatepickerLocale() {
			if (typeof $.datepicker === 'undefined' || typeof $.datepicker.setDefaults !== 'function') {
				return;
			}

			$.datepicker.regional.km = {
				monthNames: KH_MONTHS,
				monthNamesShort: KH_MONTHS,
				dayNames: KH_DAY_FULL,
				dayNamesShort: KH_DAY_SHORT,
				dayNamesMin: KH_DAY_SHORT,
				closeText: 'បិទ',
				currentText: 'ថ្ងៃនេះ',
				nextText: 'បន្ទាប់',
				prevText: 'មុន',
				firstDay: 1
			};

			$.datepicker.setDefaults($.datepicker.regional.km);
		}

		function initLegacyClassDatePickers() {
			if (typeof $.fn.datepicker !== 'function') {
				return;
			}

			var legacySelector = 'input.datepicker, input.date_picker, input.expiry_date, input.purchase_date, input.datepicker_committee';

			$(legacySelector).each(function() {
				var $input = $(this);
				if ($input.data('legacyDatePickerInit')) {
					return;
				}

				if ($input.attr('type') === 'date') {
					$input.attr('type', 'text');
				}

				$input.attr('autocomplete', 'off');
				$input.attr('inputmode', 'numeric');
				$input.attr('placeholder', 'DD-MM-YYYY');
				$input.attr('data-ui-picker-type', 'date');

				var currentValue = toUiDate($input.val());

				$input.datepicker({
					dateFormat: 'dd-mm-yy',
					changeMonth: true,
					changeYear: true,
					monthNames: KH_MONTHS,
					monthNamesShort: KH_MONTHS,
					dayNamesMin: KH_DAY_SHORT,
					showAnim: 'slideDown'
				});

				$input.val(currentValue);
				$input.data('legacyDatePickerInit', true);
			});
		}

		function initGlobalUiPickers() {
			if (typeof $.fn.datetimepicker !== 'function') {
				return;
			}

			var excludedDateClasses = '.datepicker, .date_picker, .expiry_date, .purchase_date, .datepicker_committee';

			$('input[type="date"]').not(excludedDateClasses).each(function() {
				var $input = $(this);
				if ($input.data('uiPickerInit')) {
					return;
				}

				var currentValue = toUiDate($input.val());
				$input.attr('type', 'text').attr('autocomplete', 'off').attr('placeholder', 'DD-MM-YYYY');
				$input.attr('inputmode', 'numeric');
				$input.attr('data-ui-picker-type', 'date');
				$input.datetimepicker({
					timepicker: false,
					lang: 'km',
					format: 'd-m-Y',
					scrollInput: false,
					closeOnDateSelect: true
				});
				$input.val(currentValue);
				$input.data('uiPickerInit', true);
			});

			$('input[type="time"]').each(function() {
				var $input = $(this);
				if ($input.data('uiPickerInit')) {
					return;
				}

				var currentValue = $input.val();
				$input.attr('type', 'text').attr('autocomplete', 'off').attr('placeholder', 'HH:mm');
				$input.attr('data-ui-picker-type', 'time');
				$input.datetimepicker({
					datepicker: false,
					lang: 'km',
					format: 'H:i',
					step: 5,
					scrollInput: false
				});
				$input.val(currentValue);
				$input.data('uiPickerInit', true);
			});

			$('input[type="datetime-local"]').each(function() {
				var $input = $(this);
				if ($input.data('uiPickerInit')) {
					return;
				}

				var currentValue = toUiDateTime($input.val());
				$input.attr('type', 'text').attr('autocomplete', 'off').attr('placeholder', 'DD-MM-YYYY HH:mm');
				$input.attr('inputmode', 'numeric');
				$input.attr('data-ui-picker-type', 'datetime');
				$input.datetimepicker({
					lang: 'km',
					format: 'd-m-Y H:i',
					step: 5,
					scrollInput: false
				});
				$input.val(currentValue);
				$input.data('uiPickerInit', true);
			});
		}

		function refreshAllPickers() {
			forceUiDateFormatOnPlugins();
			initDatePickerLocale();
			initJqueryUiDatepickerLocale();
			initLegacyClassDatePickers();
			initGlobalUiPickers();
		}

		function schedulePickerRefresh() {
			refreshAllPickers();
			setTimeout(refreshAllPickers, 150);
			setTimeout(refreshAllPickers, 500);
			setTimeout(refreshAllPickers, 1200);
		}

		function observeDomForDateInputs() {
			if (typeof MutationObserver === 'undefined' || !document.body) {
				return;
			}

			var observer = new MutationObserver(function(mutations) {
				for (var i = 0; i < mutations.length; i++) {
					var mutation = mutations[i];
					if (mutation.type === 'childList' && (mutation.addedNodes && mutation.addedNodes.length > 0)) {
						schedulePickerRefresh();
						return;
					}

					if (mutation.type === 'attributes' && mutation.target && mutation.target.tagName === 'INPUT') {
						schedulePickerRefresh();
						return;
					}
				}
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['type', 'class', 'value']
			});
		}

		$(document).ready(function() {
			schedulePickerRefresh();
			observeDomForDateInputs();
		});

		$(window).on('load', function() {
			schedulePickerRefresh();
		});

		$(document).on('focus', 'input[type="date"], input[type="time"], input[type="datetime-local"]', function() {
			refreshAllPickers();
		});

		$(document).on('focus', 'input.datepicker, input.date_picker, input.expiry_date, input.purchase_date, input.datepicker_committee', function() {
			refreshAllPickers();
		});

		$(document).ajaxComplete(function() {
			schedulePickerRefresh();
		});

		$(document).on('blur', 'input[data-ui-picker-type="date"]', function() {
			var $input = $(this);
			var parsed = parseUiDate($input.val());
			if ($input.val().trim() === '') {
				$input.removeClass('is-invalid');
				return;
			}

			if (!parsed) {
				$input.addClass('is-invalid');
				return;
			}

			$input.val(parsed.dd + '-' + parsed.mm + '-' + parsed.yyyy);
			$input.removeClass('is-invalid');
		});

		$(document).on('blur', 'input[data-ui-picker-type="datetime"]', function() {
			var $input = $(this);
			var parsed = parseUiDateTime($input.val());
			if ($input.val().trim() === '') {
				$input.removeClass('is-invalid');
				return;
			}

			if (!parsed) {
				$input.addClass('is-invalid');
				return;
			}

			$input.val(parsed.dd + '-' + parsed.mm + '-' + parsed.yyyy + ' ' + parsed.hh + ':' + parsed.ii);
			$input.removeClass('is-invalid');
		});

		$(document).on('submit', 'form', function(e) {
			var hasInvalidDate = false;

			$(this).find('input[data-ui-picker-type="date"]').each(function() {
				var $input = $(this);
				var converted = toStorageDate($input.val());
				if (converted === null) {
					hasInvalidDate = true;
					$input.addClass('is-invalid');
					return;
				}

				$input.removeClass('is-invalid');
				$input.val(converted);
			});

			$(this).find('input[data-ui-picker-type="datetime"]').each(function() {
				var $input = $(this);
				var converted = toStorageDateTime($input.val());
				if (converted === null) {
					hasInvalidDate = true;
					$input.addClass('is-invalid');
					return;
				}

				$input.removeClass('is-invalid');
				$input.val(converted);
			});

			if (hasInvalidDate) {
				e.preventDefault();
				alert('Invalid date format. Use DD-MM-YYYY or YYYY-MM-DD (and HH:mm for time).');
			}
		});
	})(jQuery);
</script>

