jQuery(function ($) {
    let wllp_ajax_url = wllp_localize_data.ajax_url;
    let wllp_nonce = wllp_localize_data.nonce;
    let saving_button_label = wllp_localize_data.saving_button_label ?? '';
    let saved_button_label = wllp_localize_data.saved_button_label ?? '';


    const wllp_functions = {
        init: function () {
            this.event_listeners();
        },

        save_settings: function (section) {
            // Validate grace period days before submission
            if (!this.validateGracePeriodDays()) {
                return;
            }

            let data = $(section).serialize();
            $.ajax({
                type: 'post',
                url: wllp_ajax_url,
                data: {
                    data: data,
                    nonce: wllp_nonce,
                    action: 'wllp_save_settings',
                },
                error: function (request, error) {
                },
                success: function (json) {
                    alertify.set('notifier', 'position', 'top-right');
                    $('#wllp-settings #wllp-setting-submit-button').attr('disabled', false);
                    $("#wllp-settings #wllp-setting-submit-button span").html(saved_button_label);
                    $("#wllp-settings .wllp-button-block .spinner").removeClass("is-active");
                    if (json.error) {
                        if (json.message) {
                            alertify.error(json.message);
                        }

                        if (json.field_error) {
                            wllp_jquery.each(json.field_error, function (index, value) {
                                //alertify.error(value);
                                wllp_jquery(`#wllp-settings #wllp-settings_form .wllp_${index}_value_block`).after('<span class="wllp-error" style="color: red;">' + value + '</span>');
                            });
                        }
                    } else {
                        alertify.success(json.message);
                        setTimeout(function () {
                            $("#wllp-settings .wllp-button-block .spinner").removeClass("is-active");
                            location.reload();
                        }, 800);
                    }
                    if (json.redirect) {
                        window.location.href = json.redirect;
                    }
                }
            });
        },

        validateGracePeriodDays: function () {
            let checkbox = $('#wllp-settings .wllp-grace-period-checkbox');
            let daysInput = $('#wllp-settings .wllp-grace-period-days-input');
            alertify.set('notifier', 'position', 'top-right');
            // Only validate if grace period is enabled
            if (!checkbox.is(':checked')) {
                return true;
            }
            
            // Check if grace period section is visible (only for applicable point types)
            let gracePeriodSection = $('#wllp-settings .wllp-grace-period-section');
            if (gracePeriodSection.is(':hidden')) {
                return true;
            }
            
            let daysValue = daysInput.find('input[type="number"]').val();
            
            // Check if empty
            if (!daysValue || daysValue.trim() === '') {
                alertify.error('Grace period days cannot be empty');
                return false;
            }
            
            // Check if negative
            if (parseInt(daysValue) < 0) {
                alertify.error('Grace period days cannot be negative');
                return false;
            }
            
            return true;
        },

        event_listeners: function () {
            $('#wllp-main #wllp-settings #wllp-setting-submit-button').click(function (event) {
                event.preventDefault();
                wllp_functions.save_settings(this.closest('#wllp-settings_form'));
            });

            $('#wllp-settings .wllp-level-points-based').change(function () {
                let option = $(this).val();
                let orderSection = $(this).closest('.wllp-setting-body').find('.wllp-order-field-inputs');
                let gracePeriodSection = $(this).closest('.wllp-setting-body').find('.wllp-grace-period-section');

                if (option === 'from_order_total') {
                    orderSection.show();
                } else {
                    orderSection.hide();
                }

                if (option === 'from_current_balance' || option === 'from_points_redeemed') {
                    gracePeriodSection.show();
                } else {
                    gracePeriodSection.hide();
                }
            });

            $('#wllp-settings .wllp-grace-period-checkbox').change(function () {
                let isChecked = $(this).is(':checked');
                let daysInput = $(this).closest('.wllp-grace-period-section').find('.wllp-grace-period-days-input');
                let warningSection = $('.wllp-grace-period-warning');

                if (isChecked) {
                    daysInput.show();
                    warningSection.show();
                } else {
                    daysInput.hide();
                    warningSection.hide();
                }
            });
        },
    }

    /* Init */
    $(document).ready(function () {
        wllp_functions.init();
    });
});