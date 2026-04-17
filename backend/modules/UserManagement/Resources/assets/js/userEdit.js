$('.select-basic-single').select2();

function refreshEditUserModal() {
    var userId = $('#id').val();
    if (typeof detailsView === 'function' && userId) {
        detailsView(userId);
        return;
    }
    location.reload();
}

function reloadUserTable() {
    if ($.fn.DataTable && $('#user-table').length) {
        $('#user-table').DataTable().ajax.reload(null, false);
    }
}

function resolveAjaxError(xhr, fallback) {
    if (!xhr) {
        return fallback;
    }

    if (xhr.status === 419) {
        return 'Session expired. Please refresh the page and try again.';
    }
    if (xhr.status === 403) {
        return 'You do not have permission to perform this action.';
    }

    if (xhr.responseJSON) {
        if (xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (xhr.responseJSON.errors) {
            var firstField = Object.keys(xhr.responseJSON.errors)[0];
            if (firstField && xhr.responseJSON.errors[firstField] && xhr.responseJSON.errors[firstField][0]) {
                return xhr.responseJSON.errors[firstField][0];
            }
        }
    }

    return fallback;
}

$('#editUserForm').submit(function (e) {
    e.preventDefault();

    $('.error_full_name').html('');
    $('.error_email').html('');
    $('.error_contact_no').html('');
    $('.error_password').html('');
    $('.error_role_id').html('');
    $('.error_profile_image').html('');

    var url = $(this).attr('action');
    var method = $(this).attr('method');
    var data = new FormData(this);

    $.ajax({
        url: url,
        type: method,
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        success: function (data) {
            if (data.status == 'success') {
                toastr.success(data.message);
                reloadUserTable();
                $('#editUser').modal('hide');
                $('#editUserForm').trigger('reset');
                location.reload();
                return;
            }

            if (data.errors.full_name) {
                $('.error_full_name').html(data.errors.full_name[0]);
                $('#full_name').focus();
            }
            if (data.errors.email) {
                $('.error_email').html(data.errors.email[0]);
                $('#email').focus();
            }
            if (data.errors.contact_no) {
                $('.error_contact_no').html(data.errors.contact_no[0]);
                $('#contact_no').focus();
            }
            if (data.errors.password) {
                $('.error_password').html(data.errors.password[0]);
                $('#password').focus();
            }
            if (data.errors.role_id) {
                $('.error_role_id').html(data.errors.role_id[0]);
                $('#role_id').focus();
            }
            if (data.errors.profile_image) {
                $('.error_profile_image').html(data.errors.profile_image[0]);
                $('#profile_image').focus();
            }
        }
    });
});

$(document).on('click', '.user-device-create-btn', function () {
    $('.device_error').html('');

    var $btn = $(this);
    var url = $btn.data('url');
    var userId = $btn.data('user-id');
    var csrf = $('meta[name="csrf-token"]').attr('content');

    var payload = {
        _token: csrf,
        device_id: $('#device_create_id').val(),
        device_name: $('#device_create_name').val(),
        platform: $('#device_create_platform').val(),
        status: $('#device_create_status').val(),
        imei: $('#device_create_imei').val(),
        fingerprint: $('#device_create_fingerprint').val(),
        rejection_reason: $('#device_create_reason').val()
    };

    $.ajax({
        url: url,
        type: 'POST',
        data: payload,
        success: function (data) {
            if (data.status === 'success') {
                toastr.success(data.message);
                reloadUserTable();
                refreshEditUserModal(userId);
                return;
            }

            if (data.errors && data.errors.device_id) {
                $('.device_error').html(data.errors.device_id[0]);
                return;
            }

            $('.device_error').html(data.message || 'Unable to add device.');
        },
        error: function (xhr) {
            var msg = resolveAjaxError(xhr, 'Unable to add device.');
            $('.device_error').html(msg);
            toastr.error(msg);
        }
    });
});

$(document).on('click', '.user-device-status-btn', function () {
    var $btn = $(this);
    var url = $btn.data('url');
    var userId = $btn.data('user-id');
    var status = $btn.data('status');
    var requiresReason = String($btn.data('requires-reason')) === '1';
    var csrf = $('meta[name="csrf-token"]').attr('content');

    var reason = '';
    if (requiresReason) {
        var input = prompt('Please enter rejection reason (optional):', '');
        if (input === null) {
            return;
        }
        reason = input;
    }

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: csrf,
            user_id: userId,
            status: status,
            rejection_reason: reason
        },
        success: function (data) {
            if (data.status === 'success') {
                toastr.success(data.message);
                reloadUserTable();
                refreshEditUserModal();
                return;
            }
            toastr.error(data.message || 'Unable to update device status.');
        },
        error: function (xhr) {
            var msg = resolveAjaxError(xhr, 'Unable to update device status.');
            toastr.error(msg);
        }
    });
});

$(document).on('click', '.user-device-delete-btn', function () {
    var $btn = $(this);
    var url = $btn.data('url');
    var userId = $btn.data('user-id');
    var csrf = $('meta[name="csrf-token"]').attr('content');

    var executeDelete = function () {
        $.ajax({
            url: url,
            type: 'DELETE',
            data: {
                _token: csrf,
                user_id: userId
            },
            success: function (data) {
                if (data.status === 'success') {
                    toastr.success(data.message);
                    reloadUserTable();
                    refreshEditUserModal();
                    return;
                }
                toastr.error(data.message || 'Unable to delete device.');
            },
            error: function (xhr) {
                var msg = resolveAjaxError(xhr, 'Unable to delete device.');
                toastr.error(msg);
            }
        });
    };

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will remove the selected device registration.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                executeDelete();
            }
        });
        return;
    }

    if (confirm('Delete this device registration?')) {
        executeDelete();
    }
});

