jQuery(function ($) {
    let wllp_ajax_url = wllp_localize_data.ajax_url;
    let wllp_nonce = wllp_localize_data.nonce;
    let saving_button_label = wllp_localize_data.saving_button_label ?? '';
    let saved_button_label = wllp_localize_data.saved_button_label ?? '';
    let wllp_options_from_which_grace_based_on = wllp_localize_data.grace_based_on_options ?? [];

    const wllp_functions = {
        init: function () {
            this.event_listeners();
        },

        save_settings: function (section) {
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
                        $('#wllp-settings #wllp-settings_form .wllp-error').remove();
                        if (json.message) {
                            alertify.error(json.message);
                        }
                        if (json.field_error) {
                            $.each(json.field_error, function (index, value) {
                                //alertify.error(value);
                                $(`#wllp-settings #wllp-settings_form .wllp_${index}_value_block`).after('<span class="wllp-error" style="color: red;margin-left: 26px;">' + value + '</span>');
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
                if (wllp_options_from_which_grace_based_on.includes(option)) {
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

            $('#grace_period_days').on('keypress', function (e) {
                if (isNaN(e.key)) {
                    e.preventDefault();
                }
            });
        },
    }

    /* Init */
    $(document).ready(function () {
        wllp_functions.init();
    });
});