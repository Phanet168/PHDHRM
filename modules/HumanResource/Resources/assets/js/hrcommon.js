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

    // yyyy-mm-dd
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      const parts = value.split('-');
      return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    // dd/mm/yyyy
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
    const $selectedOption = $select.find('option:selected');

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

    let lines = [];
    lines.push(`<strong>${policyLabel}</strong>`);

    if (entitlementValue !== undefined && entitlementValue !== null && String(entitlementValue) !== '') {
      lines.push(`សិទ្ធិច្បាប់៖ ${entitlementValue} ${entitlementUnit} / ${entitlementScope}`);
    }

    if (maxPerRequest !== undefined && maxPerRequest !== null && String(maxPerRequest) !== '') {
      lines.push(`អតិបរមាក្នុងមួយសំណើ៖ ${maxPerRequest} ${entitlementUnit}`);
    }

    lines.push(`បៀវត្ស៖ ${isPaid ? 'មានបៀវត្ស' : 'គ្មានបៀវត្ស'}`);
    lines.push(`ឯកសារភ្ជាប់៖ ${(requiresAttachment || requiresMedical) ? 'ត្រូវភ្ជាប់' : 'មិនតម្រូវ'}`);

    if (requiresMedical) {
      lines.push('តម្រូវវិញ្ញាបនបត្រពេទ្យ');
    }

    $hintBox.html(lines.join(' | '));

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
        .html('Loading leave balance...');
      return;
    }

    if (!payload || payload.ok !== true) {
      $hintBox
        .removeClass('alert-info alert-success alert-warning alert-danger')
        .addClass('alert-secondary')
        .html('Please select employee and leave type to view balance.');
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
    const yearText = payload.financial_year_label ? ` | Year: ${payload.financial_year_label}` : '';

    let lines = [];
    lines.push(`<strong>${scopeLabel}</strong>${yearText}`);
    lines.push(`Entitled: ${entitled} ${unitLabel}`);

    if (!Number.isNaN(approvedTaken)) {
      lines.push(`Approved: ${approvedTaken} ${unitLabel}`);
    }
    if (!Number.isNaN(pendingReserved)) {
      lines.push(`Pending: ${pendingReserved} ${unitLabel}`);
    }
    if (remaining !== null && !Number.isNaN(remaining)) {
      lines.push(`Remaining: <strong>${remaining}</strong> ${unitLabel}`);
    }
    if (maxPerRequest !== null && !Number.isNaN(maxPerRequest) && maxPerRequest > 0) {
      lines.push(`Max per request: ${maxPerRequest} ${unitLabel}`);
    }

    const isLow = remaining !== null && !Number.isNaN(remaining) && remaining <= 0;
    $hintBox
      .removeClass('alert-info alert-secondary alert-success alert-warning alert-danger')
      .addClass(isLow ? 'alert-warning' : 'alert-success')
      .html(lines.join(' | '));
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
      alert('Start Days Cannot be Grater than End Date')
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
            })
            location.reload();
          }
        }
      });
    }
  })

});

$(document).on('click', '.edit-application', function () {
  var url = $(this).data('url');
  $.ajax({
    url: url,
    type: "GET",
    success: function (response) {
      $('#editLeaveApplication').html(response);
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

$(document).ready(function() {

  $('#leave-application-filter').click(function() {
      var table = $('#leave-application-table');
      table.on('preXhr.dt', function(e, settings, data) {
          data.employee_id = $('#employee_name').val();
      });
      table.DataTable().ajax.reload();
  });

  $('#leave-application-search-reset').click(function() {
      $('#employee_name').val('').trigger('change');
      var table = $('#leave-application-table');
      table.on('preXhr.dt', function(e, settings, data) {
          data.employee_id = '';

          $("#employee_name").select2({
              placeholder: "All Employees"
          });
      });
      table.DataTable().ajax.reload();
  });
});
