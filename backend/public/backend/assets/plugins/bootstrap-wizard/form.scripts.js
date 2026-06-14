"use strict"; // Start of use strict
function scroll_to_class(element_class, removed_height) {
    var scroll_to = $(element_class).offset().top - removed_height;
    if ($(window).scrollTop() !== scroll_to) {
        $('html, body').stop().animate({scrollTop: scroll_to}, 0);
    }
}

function bar_progress(progress_line_object, direction) {
    var number_of_steps = progress_line_object.data('number-of-steps');
    var now_value = progress_line_object.data('now-value');
    var new_value = 0;
    if (direction === 'right') {
        new_value = now_value + (100 / number_of_steps);
    } else if (direction === 'left') {
        new_value = now_value - (100 / number_of_steps);
    }
    progress_line_object.attr('style', 'width: ' + new_value + '%;').data('now-value', new_value);
}

function wizardShouldValidateField($field) {
    if (typeof shouldValidateRequiredField === 'function') {
        return shouldValidateRequiredField($field);
    }

    return $field.is(':enabled') && $field.is(':visible') && !$field.hasClass('skip-required');
}

function wizardFieldHasValue($form, $field) {
    if (typeof fieldHasValue === 'function') {
        return fieldHasValue($form, $field);
    }

    var type = (($field.attr('type') || '') + '').toLowerCase();

    if (type === 'radio' || type === 'checkbox') {
        var name = $field.attr('name');
        if (!name) {
            return $field.is(':checked');
        }
        return $form.find('[name="' + name + '"]:checked').length > 0;
    }

    if ($field.is('select') && $field.prop('multiple')) {
        var values = $field.val() || [];
        return Array.isArray(values) ? values.length > 0 : false;
    }

    return $.trim(($field.val() || '') + '') !== '';
}

function wizardValidationMessage() {
    if (typeof validationMessage === 'function') {
        return validationMessage();
    }
    return 'Please fill all required fields.';
}

function wizardFocusFirstError($form) {
    if (typeof jumpToFirstValidationError === 'function') {
        jumpToFirstValidationError($form);
        return;
    }

    var $firstError = $form.find('.input-error, .is-invalid').filter(':enabled:visible').first();
    if (!$firstError.length) {
        return;
    }

    $('html, body').stop().animate({ scrollTop: Math.max($firstError.offset().top - 120, 0) }, 0);
    $firstError.trigger('focus');
}

function wizardShowValidationError($form) {
    if (typeof toastr !== 'undefined' && toastr && typeof toastr.error === 'function') {
        toastr.error(wizardValidationMessage());
    }

    wizardFocusFirstError($form);
}

jQuery(document).ready(function () {

    // Form

    $('.f1 fieldset:first').fadeIn('slow');

    $('.f1 .required-field').on('focus', function () {
        $(this).removeClass('input-error');
    });

    // next step
    $('.f1 .btn-next').on('click', function () {
        var $form = $(this).parents('.f1');
        var parent_fieldset = $(this).parents('fieldset');
        var next_step = true;
        // navigation steps / progress steps
        var current_active_step = $(this).parents('.f1').find('.f1-step.active');
        var progress_line = $(this).parents('.f1').find('.f1-progress-line');

        // fields validation
        parent_fieldset.find('.required-field').each(function () {
            var $field = $(this);
            if (!wizardShouldValidateField($field)) {
                $field.removeClass('input-error');
                return;
            }

            if (!wizardFieldHasValue($form, $field)) {
                $field.addClass('input-error');
                next_step = false;
            } else {
                $field.removeClass('input-error');
            }
        });
        // fields validation

        if (next_step) {
            parent_fieldset.fadeOut(400, function () {
                // change icons
                current_active_step.removeClass('active').addClass('activated').next().addClass('active');
                // progress bar
                bar_progress(progress_line, 'right');
                // show next step
                $(this).next().fadeIn();
                // scroll window to beginning of the form
                scroll_to_class($('.f1'), 20);
            });
        } else {
            wizardShowValidationError($form);
        }

    });

    // previous step
    $('.f1 .btn-previous').on('click', function () {
        // navigation steps / progress steps
        var current_active_step = $(this).parents('.f1').find('.f1-step.active');
        var progress_line = $(this).parents('.f1').find('.f1-progress-line');

        $(this).parents('fieldset').fadeOut(400, function () {
            // change icons
            current_active_step.removeClass('active').prev().removeClass('activated').addClass('active');
            // progress bar
            bar_progress(progress_line, 'left');
            // show previous step
            $(this).prev().fadeIn();
            // scroll window to beginning of the form
            scroll_to_class($('.f1'), 20);
        });
    });

    // submit
    $('.f1').on('submit', function (e) {
        var $form = $(this);
        var hasError = false;

        // fields validation
        $form.find('.required-field').each(function () {
            var $field = $(this);
            if (!wizardShouldValidateField($field)) {
                $field.removeClass('input-error');
                return;
            }

            if (!wizardFieldHasValue($form, $field)) {
                hasError = true;
                $field.addClass('input-error');
            } else {
                $field.removeClass('input-error');
            }
        });
        // fields validation

        if (hasError) {
            e.preventDefault();
            wizardShowValidationError($form);
        }

    });


    //show and hide disability input
    $('.disabilities_desc').parent().closest('.cust_border').hide();
    if($('input[type=radio][name=is_disable]').val() == 1){
        $('.disabilities_desc').parent().closest('.cust_border').show(); 
    } else {
        $('.disabilities_desc').parent().closest('.cust_border').hide();
    }
    $('input[type=radio][name=is_disable]').change(function() {
        if (this.value == 1) {
            $('.disabilities_desc').parent().closest('.cust_border').show();
        }
        else if (this.value == 0) {                        
            $('.disabilities_desc').parent().closest('.cust_border').hide();
        }
    });

    //get gross salary from basics and allowences
    $( "#basic_amount, .allowances" ).keyup(function( event ) {
        let basic_amount = $('#basic_amount').val() ? $('#basic_amount').val() : 0;
        let allowance_amount = 0;
        $('.allowances').each(function(){
            allowance_amount += parseFloat($(this).val() ? $(this).val() : 0);
        })
        let gross_amount = parseFloat(basic_amount) + allowance_amount;
        $('#gross_salary').val(gross_amount);
    });


    var duty_type = $('#duty_type').find(":selected").val();
    if(duty_type == 3){
        $('.contractual').parent().parent().show();
    } else {
        $('.contractual').parent().parent().hide();
    }
    $('#duty_type').on('change', function() {
        if( this.value == 3 ){
            $('.contractual').parent().parent().show();
        } else {    
              $('input.contractual').val('');
              $('.contractual').parent().parent().hide();
        }
    });



});
