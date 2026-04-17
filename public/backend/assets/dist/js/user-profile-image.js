function previewFile(input) {
    "use strict"
    var preview = input.previousElementSibling;
    var file    = input.files[0];
    var reader  = new FileReader();

    if (input.files[0].size > 2000000) {
        alert("Maximum file size is 2MB!");

    } else {
        reader.onloadend = function () {
            preview.src = reader.result;
        };

        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = "";
        }
    }
}

function profilePicture(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#profilePicturePreview').css('background-image', 'url(' + e.target.result + ')');
            $('#profilePicturePreview').fadeIn(650);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

$("#profilePictureUpload").change(function() {
    profilePicture(this);
});

function userSignature(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#signaturePreview').css('background-image', 'url(' + e.target.result + ')');
            $('#signaturePreview').fadeIn(650);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

$("#signatureUpload").change(function() {
    userSignature(this);
});

$('#updateProfile .userProfileUpdate').on('submit', (function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    $('.error_contact_no').html('');
    $('.error_full_name').html('');
    $('.error_email').html('');

    $.ajax({
        type: 'POST',
        url: $(this).attr('action'),
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data) {
            $('#updateProfile .userProfileUpdate').trigger('reset');
            $('#updateProfile').modal('hide');
            toastr.success(data.message);
            location.reload();
        },

        error: function (data) {
            var errors = data.responseJSON && data.responseJSON.errors ? data.responseJSON.errors : {};
            
            if (errors.contact_no) {
                $('.error_contact_no').html(errors.contact_no[0]);
                $('#contact_no').focus();
            }
            if (errors.full_name) {
                $('.error_full_name').html(errors.full_name[0]);
                $('#full_name').focus();
            }
            if (errors.email) {
                $('.error_email').html(errors.email[0]);
                $('#email').focus();
            }

            if (!Object.keys(errors).length) {
                var message = (data.responseJSON && data.responseJSON.message) ? data.responseJSON.message : 'Something went wrong';
                toastr.error(message);
            }
        },
    });
}));
