$(document).ready(function () {
  "use strict";

  function parseDateValue(rawValue) {
    if (!rawValue) {
      return null;
    }

    const value = String(rawValue).trim();
    if (!value) {
      return null;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      const parts = value.split('-');
      return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
      const parts = value.split('/');
      return new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return null;
    }

    return parsed;
  }

  function updateLeaveTotalDays($context) {
    const $startInput = $context.find('.leave-start-date').first();
    const $endInput = $context.find('.leave-end-date').first();
    const $totalInput = $context.find('.leave-total-day').first();

    if (!$startInput.length || !$endInput.length || !$totalInput.length) {
      return;
    }

    const startDay = parseDateValue($startInput.val());
    const endDay = parseDateValue($endInput.val());

    if (!startDay || !endDay) {
      $totalInput.val('');
      return;
    }

    const millisecondsPerDay = 1000 * 60 * 60 * 24;
    const millisBetween = endDay.getTime() - startDay.getTime();
    const days = Math.floor(millisBetween / millisecondsPerDay);

    if (days < 0) {
      alert('Start date cannot be greater than end date.');
      $totalInput.val('');
      return;
    }

    $totalInput.val(days + 1);
  }

  function renderLeavePolicyHint($select) {
    if (!$select.length) {
      return;
    }

    const $form = $select.closest('form');
    const $hintBox = $form.find('.leave-policy-hint').first();
    let $selectedOption = $select.find('option:selected');

    if ((!$selectedOption.length || !$selectedOption.val()) && !$select.is('select')) {
      const selectedValue = ($select.val() || '').toString();
      const $metaSelect = $form.find('.leave-type-meta').first();
      if ($metaSelect.length) {
        $selectedOption = $metaSelect.find('option[value="' + selectedValue + '"]').first();
      }
    }

    if (!$hintBox.length || !$selectedOption.length || !$selectedOption.val()) {
      return;
    }

    const policyLabel = $selectedOption.data('policy-label') || '-';
    const entitlementValue = $selectedOption.data('entitlement-value');
    const entitlementUnit = $selectedOption.data('entitlement-unit') || '';
    const entitlementScope = $selectedOption.data('entitlement-scope') || '';
    const maxPerRequest = $selectedOption.data('max-per-request');
    const isPaid = parseInt($selectedOption.data('is-paid'), 10) === 1;
    const requiresAttachment = parseInt($selectedOption.data('requires-attachment'), 10) === 1;
    const requiresMedical = parseInt($selectedOption.data('requires-medical'), 10) === 1;

    const cards = [];

    if (entitlementValue !== undefined && entitlementValue !== null && String(entitlementValue) !== '') {
      cards.push(`
        <div class="leave-note-item">
          <span class="leave-note-label">សិទ្ធិច្បាប់</span>
          <strong class="leave-note-value">${entitlementValue} ${entitlementUnit}</strong>
        </div>
      `);
    }

    if (maxPerRequest !== undefined && maxPerRequest !== null && String(maxPerRequest) !== '') {
      cards.push(`
        <div class="leave-note-item">
          <span class="leave-note-label">អតិបរមា/សំណើ</span>
          <strong class="leave-note-value">${maxPerRequest} ${entitlementUnit}</strong>
        </div>
      `);
    }

    cards.push(`
      <div class="leave-note-item">
        <span class="leave-note-label">ប្រាក់បៀវត្ស</span>
        <strong class="leave-note-value">${isPaid ? 'មានប្រាក់បៀវត្ស' : 'គ្មានប្រាក់បៀវត្ស'}</strong>
      </div>
    `);

    cards.push(`
      <div class="leave-note-item">
        <span class="leave-note-label">ឯកសារភ្ជាប់</span>
        <strong class="leave-note-value">${(requiresAttachment || requiresMedical) ? 'ត្រូវភ្ជាប់' : 'មិនតម្រូវ'}</strong>
      </div>
    `);

    const metaLines = [`គោលការណ៍: ${policyLabel}`, `សុពលភាព: ${entitlementScope}`];
    if (requiresMedical) {
      metaLines.push('តម្រូវវិញ្ញាបនបត្រពេទ្យ');
    }

    $hintBox.html(`
      <div class="leave-note-head">
        <strong class="leave-note-title">${policyLabel}</strong>
      </div>
      <div class="leave-note-grid">${cards.join('')}</div>
      <div class="leave-note-meta">${metaLines.join(' • ')}</div>
    `);

    const $attachmentInput = $form.find('.leave-attachment-input').first();
    const $attachmentRequired = $form.find('.leave-attachment-required').first();
    const requireFile = requiresAttachment || requiresMedical;
    const hasOldFile = parseInt($attachmentInput.data('has-old-file'), 10) === 1;

    if ($attachmentInput.length) {
      $attachmentInput.prop('required', requireFile && !hasOldFile);
    }

    if ($attachmentRequired.length) {
      $attachmentRequired.toggleClass('d-none', !requireFile);
    }
  }

  function renderLeaveBalanceHint($form, payload, loading = false) {
    const $hintBox = $form.find('.leave-balance-hint').first();
    if (!$hintBox.length) {
      return;
    }

    if (loading) {
      $hintBox
        .removeClass('alert-secondary alert-success alert-warning alert-danger')
        .addClass('alert-info')
        .html('កំពុងទាញយកព័ត៌មានថ្ងៃឈប់សម្រាក...');
      return;
    }

    if (!payload || payload.ok !== true) {
      $hintBox
        .removeClass('alert-info alert-success alert-warning alert-danger')
        .addClass('alert-secondary')
        .html('សូមជ្រើសបុគ្គលិក និងប្រភេទច្បាប់ ដើម្បីមើលថ្ងៃនៅសល់។');
      return;
    }

    const scopeLabel = payload.scope_label || '-';
    const unitLabel = payload.unit_label || payload.unit || 'day';
    const entitled = Number(payload.entitled || 0);
    const approvedTaken = Number(payload.approved_taken || 0);
    const pendingReserved = Number(payload.pending_reserved || 0);
    const remaining = (payload.remaining === null || payload.remaining === undefined)
      ? null
      : Number(payload.remaining);
    const maxPerRequest = (payload.max_per_request === null || payload.max_per_request === undefined || payload.max_per_request === '')
      ? null
      : Number(payload.max_per_request);

    const metrics = [
      {
        label: 'សិទ្ធិសរុប',
        value: `${entitled} ${unitLabel}`,
      },
      {
        label: 'បានអនុម័ត',
        value: `${Number.isNaN(approvedTaken) ? 0 : approvedTaken} ${unitLabel}`,
      },
      {
        label: 'កំពុងរង់ចាំ',
        value: `${Number.isNaN(pendingReserved) ? 0 : pendingReserved} ${unitLabel}`,
      }
    ];

    if (remaining !== null && !Number.isNaN(remaining)) {
      metrics.push({
        label: 'នៅសល់',
        value: `${remaining} ${unitLabel}`,
        emphasis: true,
      });
    }

    if (maxPerRequest !== null && !Number.isNaN(maxPerRequest) && maxPerRequest > 0) {
      metrics.push({
        label: 'អតិបរមា/សំណើ',
        value: `${maxPerRequest} ${unitLabel}`,
      });
    }

    const metricHtml = metrics.map(function (metric) {
      return `
        <div class="leave-note-item${metric.emphasis ? ' leave-note-item-highlight' : ''}">
          <span class="leave-note-label">${metric.label}</span>
          <strong class="leave-note-value">${metric.value}</strong>
        </div>
      `;
    }).join('');

    const meta = [];
    if (scopeLabel) {
      meta.push(`សិទ្ធិ: ${scopeLabel}`);
    }
    if (payload.financial_year_label) {
      meta.push(`ឆ្នាំ: ${payload.financial_year_label}`);
    }

    const isLow = remaining !== null && !Number.isNaN(remaining) && remaining <= 0;
    $hintBox
      .removeClass('alert-info alert-secondary alert-success alert-warning alert-danger')
      .addClass(isLow ? 'alert-warning' : 'alert-success')
      .html(`
        <div class="leave-note-head">
          <strong class="leave-note-title">សមតុល្យច្បាប់</strong>
        </div>
        <div class="leave-note-grid">${metricHtml}</div>
        <div class="leave-note-meta">${meta.join(' • ')}</div>
      `);
  }

  function fetchLeaveBalance($form) {
    if (!$form || !$form.length) {
      return;
    }

    const url = $form.data('leave-balance-url');
    if (!url) {
      return;
    }

    const employeeId =
      $form.find('.leave-employee-select').first().val() ||
      $form.find('[name="employee_id"]').first().val();
    const leaveTypeId =
      $form.find('.leave-type-select').first().val() ||
      $form.find('[name="leave_type_id"]').first().val();
    const startDate =
      $form.find('.leave-start-date').first().val() ||
      $form.find('[name="leave_apply_start_date"]').first().val();
    const excludeLeaveUuid =
      $form.find('[name="leave_uuid"]').first().val() || '';

    if (!employeeId || !leaveTypeId) {
      renderLeaveBalanceHint($form, null, false);
      return;
    }

    renderLeaveBalanceHint($form, null, true);

    $.ajax({
      url: url,
      type: 'GET',
      dataType: 'json',
      data: {
        employee_id: employeeId,
        leave_type_id: leaveTypeId,
        start_date: startDate,
        exclude_leave_uuid: excludeLeaveUuid,
      },
      success: function (response) {
        renderLeaveBalanceHint($form, response, false);
      },
      error: function () {
        renderLeaveBalanceHint($form, null, false);
      }
    });
  }

  function initLeaveFormSelects($context) {
    if (!$context || !$context.length || typeof $.fn.select2 !== 'function') {
      return;
    }

    $context.find('.leave-search-select').each(function () {
      const $select = $(this);
      const $modal = $select.closest('.modal');

      if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
      }

      $select.select2({
        width: '100%',
        placeholder: $select.find('option:first').text() || 'Select option',
        allowClear: true,
        minimumResultsForSearch: 0,
        dropdownAutoWidth: true,
        dropdownParent: $modal.length ? $modal : $(document.body)
      });

      $select.off('select2:open.leaveSearch').on('select2:open.leaveSearch', function () {
        const searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
          window.setTimeout(function () {
            searchField.focus();
            searchField.click();
          }, 0);
        }
      });
    });
  }

  window.initLeaveFormSelects = initLeaveFormSelects;
  initLeaveFormSelects($(document));

  $(document).on('change', '.leave-start-date, .leave-end-date', function () {
    const $form = $(this).closest('form');
    updateLeaveTotalDays($form);
    fetchLeaveBalance($form);
  });

  $(document).on('change', '.leave-type-select', function () {
    renderLeavePolicyHint($(this));
    fetchLeaveBalance($(this).closest('form'));
  });

  $(document).on('change', '.leave-employee-select', function () {
    fetchLeaveBalance($(this).closest('form'));
  });

  $('.leave-application-form .leave-type-select').each(function () {
    renderLeavePolicyHint($(this));
  });

  $('.leave-application-form').each(function () {
    updateLeaveTotalDays($(this));
    fetchLeaveBalance($(this));
  });

  $(document).on('shown.bs.modal', '#addLeaveApplication, #edit-application', function () {
    const $modal = $(this);
    initLeaveFormSelects($modal);
    $modal.find('.leave-application-form .leave-type-select').each(function () {
      renderLeavePolicyHint($(this));
    });

    $modal.find('.leave-application-form').each(function () {
      updateLeaveTotalDays($(this));
      fetchLeaveBalance($(this));
    });
  });

  $("#approved_end_date").change(function () {
    var start = $('#approved_start_date').val();
    var end = $('#approved_end_date').val();
    var startDay = new Date(start);
    var endDay = new Date(end);
    var millisecondsPerDay = 1000 * 60 * 60 * 24;

    var millisBetween = endDay.getTime() - startDay.getTime();
    var days = millisBetween / millisecondsPerDay;
    var totalDays = Math.floor(days);
    console.log(totalDays);
    if (totalDays < 0) {
      alert('Start Days Cannot be Grater than End Date');
    } else {
      $('#approved_total_day').val(totalDays + 1);
    }
  });

  $(document).on('click', '#create_submit', function (e) {
    const $button = $(this);
    const $form = $button.closest('form');

    if (!$form.length) {
      return;
    }

    if ($button.prop('disabled')) {
      e.preventDefault();
      return;
    }

    const formEl = $form.get(0);
    if (formEl && typeof formEl.checkValidity === 'function' && !formEl.checkValidity()) {
      return;
    }

    const buttonType = ($button.attr('type') || 'submit').toLowerCase();
    if (buttonType !== 'submit') {
      e.preventDefault();
      $form.trigger('submit');
    }

    $button.prop("disabled", true);
  });
});

$(document).on('click', '.statusChange', function () {
  let url = $(this).data('route');
  let csrf = $(this).data('csrf');

  Swal.fire({
    title: 'Are you sure?',
    text: "You want to Rejected this request",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Change it!'
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: "PUT",
        dataType: 'json',
        url: url,
        data: {
          _token: csrf
        },
        success: function (data) {
          if (data.status) {
            Swal.fire({
              position: 'top-end',
              icon: 'success',
              title: 'Aplication Rejected',
              showConfirmButton: false,
              timer: 500
            });
            location.reload();
          }
        }
      });
    }
  });
});

$(document).on('click', '.edit-application', function () {
  var url = $(this).data('url');
  $.ajax({
    url: url,
    type: "GET",
    success: function (response) {
      $('#editLeaveApplication').html(response);
      initLeaveFormSelects($('#editLeaveApplication'));
      $('#edit-application').modal('show');
    }
  });
});

$(document).on('click', '.approve-application', function () {
  var url = $(this).data('url');

  $.ajax({
    url: url,
    type: "GET",
    success: function (response) {
      $('#approveLeaveApplication').html(response);
      $('#approve-application').modal('show');
    }
  });
});

$(document).ready(function () {
  function reloadLeaveApplicationTable() {
    const $table = $('#leave-application-table');
    if (!$table.length || !$.fn.DataTable.isDataTable($table)) {
      return;
    }

    const dataTable = $table.DataTable();
    const currentUrl = (typeof dataTable.ajax.url === 'function' && dataTable.ajax.url()) || '';
    const baseUrl = ($table.data('ajaxBaseUrl') || String(currentUrl).split('?')[0] || window.location.href);
    const employeeId = ($('#employee_name').val() || '').toString().trim();
    const nextUrl = employeeId ? `${baseUrl}?employee_id=${encodeURIComponent(employeeId)}` : baseUrl;

    $table.data('ajaxBaseUrl', baseUrl);
    dataTable.ajax.url(nextUrl).load();
  }

  $('#leave-application-filter').on('click', function () {
    reloadLeaveApplicationTable();
  });

  $('#leave-application-search-reset').on('click', function () {
    $('#employee_name').val('').trigger('change');
    reloadLeaveApplicationTable();
  });
});
